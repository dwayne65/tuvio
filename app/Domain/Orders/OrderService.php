<?php

namespace App\Domain\Orders;

use App\Models\Event;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\Promoter;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    public function __construct(private Discounts $discounts) {}

    /**
     * Create a pending order and atomically reserve inventory. Ticket-type rows
     * are locked FOR UPDATE so concurrent checkouts can never oversell.
     *
     * A promo code is redeemed under the same transaction (its row is locked
     * too) so a limited code can't be over-redeemed; the redemption is
     * returned if the reservation later expires.
     *
     * @param  array<int,array{ticket_type_id:int,quantity:int}>  $items
     */
    public function create(Event $event, array $buyer, array $items, ?User $user = null, ?string $promoCode = null, ?Promoter $promoter = null): Order
    {
        // Only events an admin has approved and published may sell tickets.
        if (! $event->isPublished()) {
            throw new RuntimeException('Tickets for this event are not on sale.');
        }

        $items = array_values(array_filter($items, fn ($i) => ($i['quantity'] ?? 0) > 0));
        if (empty($items)) {
            throw new RuntimeException('Select at least one ticket.');
        }

        return DB::transaction(function () use ($event, $buyer, $items, $user, $promoCode, $promoter) {
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

            $this->ensureWithinPlanTicketLimit($event);

            $promo = null;
            $discount = 0;
            if ($promoCode !== null && trim($promoCode) !== '') {
                $promo = $this->discounts->resolve($event, $promoCode, lock: true);
                $discount = $this->discounts->amount($promo, $subtotal, $event->currency);
                $promo->increment('redeemed_count');
            }

            ['commission_minor' => $commission, 'fee_minor' => $fee, 'tax_minor' => $tax] = $event->platformFee($subtotal - $discount);
            $total = $subtotal - $discount + $event->buyerCharges($subtotal - $discount);

            $order = Order::create([
                'reference' => 'TF-'.strtoupper(Str::random(8)),
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'promo_code_id' => $promo?->id,
                'promoter_id' => $promoter?->covers($event) && $event->organization->planAllows('promoters') ? $promoter->id : null,
                'buyer_name' => $buyer['name'],
                'buyer_email' => $buyer['email'],
                'buyer_phone' => $buyer['phone'] ?? null,
                'status' => $total === 0 ? 'awaiting_payment' : 'awaiting_payment',
                'currency' => $event->currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'fee_minor' => $fee,
                'tax_minor' => $tax,
                'total_minor' => $total,
                'commission_minor' => $commission,
                'idempotency_key' => (string) Str::uuid(),
                'reserved_until' => now()->addMinutes(config('ticketflow.reservation_minutes')),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            if ($promo) {
                PromoRedemption::create([
                    'promo_code_id' => $promo->id,
                    'order_id' => $order->id,
                    'amount_discounted_minor' => $discount,
                    'currency' => $event->currency,
                ]);
            }

            return $order->fresh('items');
        });
    }

    /**
     * The organizer's plan caps tickets sold per event. Runs after this order's seats are
     * reserved, so they count; throwing rolls the reservation back. Every ticket type of
     * the event is locked so concurrent orders on different types can't both slip under.
     */
    private function ensureWithinPlanTicketLimit(Event $event): void
    {
        $limit = $event->organization->currentPlan()?->ticket_limit;
        if ($limit === null) {
            return;
        }

        $types = TicketType::where('event_id', $event->id)->lockForUpdate()->get();
        if ($types->sum('quantity_sold') + $types->sum('quantity_reserved') > $limit) {
            throw new RuntimeException('This event has sold all the tickets its organizer can offer right now.');
        }
    }

    /**
     * Release inventory for an order whose reservation expired or was cancelled.
     * Runs at most once per order: the order row is locked, and clearing
     * `reserved_until` marks the hold as returned to stock.
     */
    public function releaseReservation(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'awaiting_payment' || $order->reserved_until === null) {
                return;
            }

            foreach ($order->items as $item) {
                TicketType::whereKey($item->ticket_type_id)
                    ->lockForUpdate()->firstOrFail()
                    ->decrement('quantity_reserved', $item->quantity);
            }

            // Give the promo code use back so another buyer can redeem it.
            if ($order->promo_code_id) {
                PromoCode::whereKey($order->promo_code_id)->lockForUpdate()->first()
                    ?->decrement('redeemed_count');
                PromoRedemption::where('order_id', $order->id)->delete();
            }
            $order->update(['status' => 'expired', 'reserved_until' => null]);
        });
    }
}
