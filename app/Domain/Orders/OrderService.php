<?php

namespace App\Domain\Orders;

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    /**
     * Create a pending order and atomically reserve inventory. Ticket-type rows
     * are locked FOR UPDATE so concurrent checkouts can never oversell.
     *
     * @param  array<int,array{ticket_type_id:int,quantity:int}>  $items
     */
    public function create(Event $event, array $buyer, array $items, ?User $user = null): Order
    {
        $items = array_values(array_filter($items, fn ($i) => ($i['quantity'] ?? 0) > 0));
        if (empty($items)) {
            throw new RuntimeException('Select at least one ticket.');
        }

        return DB::transaction(function () use ($event, $buyer, $items, $user) {
            $commissionBps = $event->commission_bps
                ?? $event->organization->commission_bps
                ?? config('ticketflow.commission_bps');

            $subtotal = 0;
            $lines = [];

            foreach ($items as $item) {
                /** @var TicketType $type */
                $type = TicketType::whereKey($item['ticket_type_id'])
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $item['quantity'];

                if (! $type->isOnSale()) {
                    throw new RuntimeException("“{$type->name}” is not currently on sale.");
                }
                if ($qty > $type->max_per_order) {
                    throw new RuntimeException("Maximum {$type->max_per_order} “{$type->name}” tickets per order.");
                }

                $available = $type->availableQuantity();
                if ($available !== null && $qty > $available) {
                    throw new RuntimeException("Only {$available} “{$type->name}” tickets remain.");
                }

                // Reserve inventory now; released if payment never completes.
                $type->increment('quantity_reserved', $qty);

                $lineTotal = $type->price_minor * $qty;
                $subtotal += $lineTotal;
                $lines[] = [
                    'ticket_type_id' => $type->id,
                    'description' => $type->name,
                    'unit_price_minor' => $type->price_minor,
                    'currency' => $event->currency,
                    'quantity' => $qty,
                    'line_total_minor' => $lineTotal,
                ];
            }

            $discount = 0;
            $commission = (int) round(($subtotal - $discount) * $commissionBps / 10000);
            $total = $subtotal - $discount;

            $order = Order::create([
                'reference' => 'TF-'.strtoupper(Str::random(8)),
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'buyer_name' => $buyer['name'],
                'buyer_email' => $buyer['email'],
                'buyer_phone' => $buyer['phone'] ?? null,
                'status' => $total === 0 ? 'awaiting_payment' : 'awaiting_payment',
                'currency' => $event->currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'fee_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $total,
                'commission_minor' => $commission,
                'idempotency_key' => (string) Str::uuid(),
                'reserved_until' => now()->addMinutes(config('ticketflow.reservation_minutes')),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            return $order->fresh('items');
        });
    }

    /** Release inventory for an order whose reservation expired or was cancelled. */
    public function releaseReservation(Order $order): void
    {
        if (in_array($order->status, ['paid', 'refunded'], true)) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                TicketType::whereKey($item->ticket_type_id)
                    ->lockForUpdate()->firstOrFail()
                    ->decrement('quantity_reserved', $item->quantity);
            }
            $order->update(['status' => 'expired']);
        });
    }
}
