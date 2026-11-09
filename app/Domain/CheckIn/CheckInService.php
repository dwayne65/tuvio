<?php

namespace App\Domain\CheckIn;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Validates scanned QR tokens and marks tickets used atomically. The ticket row
 * is locked FOR UPDATE and the issued→used transition happens inside the lock,
 * so two simultaneous scans can never both succeed (no duplicate entry).
 */
class CheckInService
{
    /**
     * @return array{result:string, message:string, ticket:?array}
     */
    public function scan(Event $event, string $qrToken, User $scanner, array $ctx = []): array
    {
        return DB::transaction(function () use ($event, $qrToken, $scanner, $ctx) {
            // Offline replay: if this client scan was already synced, return its result.
            if (! empty($ctx['client_uuid'])) {
                $prior = CheckIn::where('client_uuid', $ctx['client_uuid'])->first();
                if ($prior) {
                    return $this->response($prior->result, $prior->ticket, 'Already synced.');
                }
            }

            $ticket = Ticket::where('qr_token', $qrToken)->lockForUpdate()->first();

            [$result, $message] = $this->evaluate($event, $ticket);

            if ($result === 'valid') {
                $ticket->update([
                    'status' => 'used',
                    // Offline scans keep the time they actually happened at the gate.
                    'used_at' => $ctx['scanned_at'] ?? now(),
                    'checked_in_by' => $scanner->id,
                ]);
            }

            CheckIn::create([
                'event_id' => $event->id,
                'ticket_id' => $ticket?->id,
                'scanned_by' => $scanner->id,
                'result' => $result,
                'gate' => $ctx['gate'] ?? null,
                'device_id' => $ctx['device_id'] ?? null,
                'client_uuid' => $ctx['client_uuid'] ?? null,
                'scanned_at' => $ctx['scanned_at'] ?? now(),
                'synced_at' => now(),
            ]);

            return $this->response($result, $ticket, $message);
        });
    }

    /** @return array{0:string,1:string} [result, message] */
    private function evaluate(Event $event, ?Ticket $ticket): array
    {
        if (! $ticket) {
            return ['invalid', 'Ticket not recognised.'];
        }
        if ($ticket->event_id !== $event->id) {
            return ['wrong_event', 'This ticket is for a different event.'];
        }
        if ($ticket->order && $ticket->order->status !== 'paid') {
            return ['unpaid', 'Payment for this ticket is not confirmed.'];
        }
        if (in_array($ticket->status, ['void', 'refunded'], true)) {
            return ['void', 'This ticket has been voided or refunded.'];
        }
        if ($ticket->status === 'used') {
            return ['duplicate', 'Already checked in'.($ticket->used_at ? ' at '.$ticket->used_at->format('H:i') : '').'.'];
        }

        return ['valid', 'Entry permitted.'];
    }

    private function response(string $result, ?Ticket $ticket, string $message): array
    {
        return [
            'result' => $result,            // valid | duplicate | invalid | wrong_event | unpaid | void
            'message' => $message,
            'ticket' => $ticket ? [
                'code' => $ticket->code,
                'type' => $ticket->ticketType?->name,
                'attendee_name' => $ticket->attendee_name,
            ] : null,
        ];
    }
}
