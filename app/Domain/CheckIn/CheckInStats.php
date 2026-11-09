<?php

namespace App\Domain\CheckIn;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Live attendance figures for the check-in dashboard, computed from real
 * tickets and scan records. Time bucketing happens in PHP so the queries stay
 * portable across SQLite and PostgreSQL.
 */
class CheckInStats
{
    /** Minutes per bar on the check-ins-over-time chart. */
    private const BUCKET_MINUTES = 15;

    /**
     * @return array{
     *     sold: int, checked_in: int, remaining: int, percent: int,
     *     scans: array<string, int>,
     *     by_type: list<array{name: string, tier: string, sold: int, checked_in: int}>,
     *     by_gate: list<array{gate: string, checked_in: int}>,
     *     timeline: list<array{at: string, count: int}>,
     *     recent: list<array{result: string, attendee: ?string, type: ?string, code: ?string, gate: ?string, scanner: ?string, at: string}>,
     *     active_scanners: int
     * }
     */
    public function forEvent(Event $event): array
    {
        $tickets = Ticket::where('event_id', $event->id)
            ->whereIn('status', ['issued', 'used'])
            ->selectRaw('ticket_type_id, status, count(*) as total')
            ->groupBy('ticket_type_id', 'status')
            ->get();

        $sold = (int) $tickets->sum('total');
        $checkedIn = (int) $tickets->where('status', 'used')->sum('total');

        $byType = $event->ticketTypes()->orderBy('sort')->get()->map(fn ($type) => [
            'name' => $type->name,
            'tier' => $type->resolvedTier(),
            'sold' => (int) $tickets->where('ticket_type_id', $type->id)->sum('total'),
            'checked_in' => (int) $tickets->where('ticket_type_id', $type->id)->where('status', 'used')->sum('total'),
        ])->filter(fn ($row) => $row['sold'] > 0)->values()->all();

        $scans = CheckIn::where('event_id', $event->id)
            ->selectRaw('result, count(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result')
            ->map(fn ($n) => (int) $n)
            ->all();

        $byGate = CheckIn::where('event_id', $event->id)
            ->where('result', 'valid')
            ->selectRaw('gate, count(*) as total')
            ->groupBy('gate')
            ->get()
            ->map(fn ($row) => ['gate' => $row->gate ?: 'No gate selected', 'checked_in' => (int) $row->total])
            ->sortByDesc('checked_in')
            ->values()
            ->all();

        return [
            'sold' => $sold,
            'checked_in' => $checkedIn,
            'remaining' => max(0, $sold - $checkedIn),
            'percent' => $sold > 0 ? (int) round($checkedIn / $sold * 100) : 0,
            'scans' => $scans,
            'by_type' => $byType,
            'by_gate' => $byGate,
            'timeline' => $this->timeline($event),
            'recent' => $this->recent($event),
            'active_scanners' => CheckIn::where('event_id', $event->id)
                ->where('scanned_at', '>=', now()->subMinutes(15))
                ->distinct()
                ->count('scanned_by'),
        ];
    }

    /** @return list<array{at: string, count: int}> */
    private function timeline(Event $event): array
    {
        $times = CheckIn::where('event_id', $event->id)
            ->where('result', 'valid')
            ->orderBy('scanned_at')
            ->pluck('scanned_at');

        if ($times->isEmpty()) {
            return [];
        }

        $bucketSeconds = self::BUCKET_MINUTES * 60;
        $counts = $times->countBy(fn (Carbon $at) => intdiv($at->getTimestamp(), $bucketSeconds));

        // Fill empty buckets between the first and last scan so the chart reads as a timeline.
        $rows = [];
        for ($bucket = $counts->keys()->min(); $bucket <= $counts->keys()->max(); $bucket++) {
            $rows[] = [
                'at' => Carbon::createFromTimestamp($bucket * $bucketSeconds)->toIso8601String(),
                'count' => (int) ($counts[$bucket] ?? 0),
            ];
        }

        return array_slice($rows, -96);
    }

    /** @return list<array{result: string, attendee: ?string, type: ?string, code: ?string, gate: ?string, scanner: ?string, at: string}> */
    private function recent(Event $event): array
    {
        return CheckIn::where('event_id', $event->id)
            ->with(['ticket.ticketType', 'scanner'])
            ->latest('scanned_at')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (CheckIn $scan) => [
                'result' => $scan->result,
                'attendee' => $scan->ticket?->attendee_name,
                'type' => $scan->ticket?->ticketType?->name,
                'code' => $scan->ticket?->code,
                'gate' => $scan->gate,
                'scanner' => $scan->scanner?->name,
                'at' => $scan->scanned_at->toIso8601String(),
            ])
            ->all();
    }
}
