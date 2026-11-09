<?php

namespace App\Domain\Tickets;

use App\Mail\TicketsIssuedMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketIssuer
{
    /**
     * Issue one ticket per purchased seat for a paid order. Idempotent: if the
     * order already has tickets it returns them without issuing duplicates.
     * Newly issued tickets are emailed to the buyer once the transaction commits.
     *
     * @return Collection<int,Ticket>
     */
    public function issueForOrder(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            // Lock the order row to serialise concurrent fulfilment attempts.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->tickets()->exists()) {
                return $order->tickets()->get();
            }

            $tickets = collect();

            foreach ($order->items()->with('ticketType')->get() as $item) {
                /** @var TicketType $type */
                $type = TicketType::whereKey($item->ticket_type_id)->lockForUpdate()->firstOrFail();

                for ($i = 0; $i < $item->quantity; $i++) {
                    $tickets->push(Ticket::create([
                        'event_id' => $order->event_id,
                        'ticket_type_id' => $type->id,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'code' => $this->uniqueCode(),
                        'qr_token' => $this->secureToken(),
                        'attendee_name' => $order->buyer_name,
                        'attendee_email' => $order->buyer_email,
                        'status' => 'issued',
                        'issued_at' => now(),
                    ]));
                }

                // Convert the reservation into a confirmed sale. A hold that already
                // expired was returned to stock, so only the sale is recorded.
                if ($order->reserved_until !== null) {
                    $type->decrement('quantity_reserved', $item->quantity);
                }
                $type->increment('quantity_sold', $item->quantity);
            }

            DB::afterCommit(fn () => $this->emailBuyer($order));

            return $tickets;
        });
    }

    /** Email the tickets to the buyer. Failures are reported, never allowed to break fulfilment. */
    private function emailBuyer(Order $order): void
    {
        if (! $order->buyer_email) {
            return;
        }

        try {
            Mail::to($order->buyer_email, $order->buyer_name)
                ->send(new TicketsIssuedMail($order->fresh(['event', 'tickets.ticketType'])));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'TKT-'.strtoupper(Str::random(10));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }

    /** A non-guessable opaque token embedded in the QR code. */
    private function secureToken(): string
    {
        do {
            $token = bin2hex(random_bytes(24)); // 48 hex chars
        } while (Ticket::where('qr_token', $token)->exists());

        return $token;
    }
}
