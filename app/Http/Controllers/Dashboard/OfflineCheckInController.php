<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\CheckIn\CheckInService;
use App\Domain\CheckIn\CheckInStats;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Offline gate scanning. The manifest carries SHA-256 hashes of QR tokens (never
 * the tokens themselves) so a device can verify tickets without a connection;
 * queued scans are replayed through the normal CheckInService on sync, which is
 * idempotent per client_uuid and authoritative for duplicates.
 */
class OfflineCheckInController extends Controller
{
    /** Scans further in the past than this are refused on sync. */
    private const MAX_OFFLINE_AGE_DAYS = 7;

    public function __construct(private CheckInService $checkIn, private CheckInStats $stats) {}

    public function manifest(Request $request, Event $event): JsonResponse
    {
        $this->authorize('checkIn', $event);

        $tickets = Ticket::where('event_id', $event->id)
            ->with(['ticketType:id,name', 'order:id,status'])
            ->get(['id', 'ticket_type_id', 'order_id', 'code', 'qr_token', 'attendee_name', 'status', 'used_at'])
            ->map(fn (Ticket $ticket) => [
                'h' => hash('sha256', $ticket->qr_token),
                'c' => $ticket->code,
                'n' => $ticket->attendee_name,
                't' => $ticket->ticketType?->name,
                's' => $this->offlineState($ticket),
                'u' => $ticket->used_at?->toIso8601String(),
            ]);

        return response()
            ->json(['event' => $event->slug, 'generated_at' => now()->toIso8601String(), 'tickets' => $tickets])
            ->header('Cache-Control', 'no-store, private');
    }

    public function sync(Request $request, Event $event): JsonResponse
    {
        $this->authorize('checkIn', $event);

        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:120'],
            'scans' => ['required', 'array', 'max:500'],
            'scans.*.client_uuid' => ['required', 'uuid'],
            'scans.*.qr_token' => ['nullable', 'string', 'max:255', 'required_without:scans.*.code'],
            'scans.*.code' => ['nullable', 'string', 'max:40'],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.gate' => ['nullable', 'string', 'max:60'],
        ]);

        $oldest = now()->subDays(self::MAX_OFFLINE_AGE_DAYS);
        $latest = now()->addMinutes(5);

        // Replay in the order scans happened at the gate, so the earliest scan of a ticket wins.
        $scans = collect($data['scans'])->sortBy(fn ($scan) => Carbon::parse($scan['scanned_at'])->getTimestamp());

        $results = $scans->map(function (array $scan) use ($request, $event, $data, $oldest, $latest) {
            $at = Carbon::parse($scan['scanned_at']);
            if ($at->lt($oldest) || $at->gt($latest)) {
                return ['client_uuid' => $scan['client_uuid'], 'result' => 'rejected', 'message' => 'Scan time is out of range.', 'ticket' => null];
            }

            $token = $scan['qr_token'] ?? $this->tokenForCode($event, $scan['code'] ?? '');

            return ['client_uuid' => $scan['client_uuid'], ...$this->checkIn->scan($event, $token, $request->user(), [
                'client_uuid' => $scan['client_uuid'],
                'gate' => $scan['gate'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'scanned_at' => $at,
            ])];
        })->values();

        return response()->json([
            'results' => $results,
            'stats' => $this->stats->forEvent($event),
        ]);
    }

    /** What an offline device should show for this ticket, mirroring CheckInService's rules. */
    private function offlineState(Ticket $ticket): string
    {
        return match (true) {
            $ticket->order && $ticket->order->status !== 'paid' => 'unpaid',
            in_array($ticket->status, ['void', 'refunded'], true) => 'void',
            $ticket->status === 'used' => 'used',
            default => 'valid',
        };
    }

    /** Typed ticket codes (e.g. "TKT-AB12CD34EF") resolve to their token; unknown codes scan as invalid. */
    private function tokenForCode(Event $event, string $code): string
    {
        return Ticket::where('event_id', $event->id)
            ->where('code', strtoupper(trim($code)))
            ->value('qr_token') ?? 'unknown-code:'.$code;
    }
}
