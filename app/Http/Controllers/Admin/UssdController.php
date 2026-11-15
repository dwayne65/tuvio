<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\UssdSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** How the USSD channel is used: sessions, drop-off, sales, networks and gateway setup. */
class UssdController extends Controller
{
    /** @var list<int> */
    private const PERIODS = [1, 7, 30];

    public function index(Request $request): Response
    {
        $days = in_array($request->integer('days'), self::PERIODS, true) ? $request->integer('days') : 7;
        $since = now()->subDays($days);

        $sessions = UssdSession::where('started_at', '>=', $since)
            ->with(['order:id,status,total_minor,currency', 'contribution:id,status,amount_minor,currency'])
            ->get();

        $paidOrders = $sessions->filter(fn (UssdSession $s) => $s->order?->status === 'paid');
        $paidContributions = $sessions->filter(fn (UssdSession $s) => $s->contribution?->status === 'successful');

        return Inertia::render('Admin/Ussd', [
            'days' => $days,
            'periods' => self::PERIODS,
            'currency' => config('ticketflow.default_currency'),
            'setup' => [
                'shortcode' => config('ticketflow.ussd.shortcode'),
                'webhook_url' => route('webhooks.ussd'),
                'signature_configured' => (bool) config('ticketflow.ussd.public_key'),
                'events_live' => Event::public()->upcoming()->where('sell_on_ussd', true)->count(),
            ],
            'stats' => [
                'sessions' => $sessions->count(),
                'callers' => $sessions->unique('msisdn')->count(),
                'direct_dials' => $sessions->where('dialed_direct', true)->count(),
                'orders' => $sessions->whereNotNull('order_id')->count(),
                'paid_orders' => $paidOrders->count(),
                'ticket_sales_minor' => (int) $paidOrders->sum(fn (UssdSession $s) => $s->order->total_minor),
                'contributions_minor' => (int) $paidContributions->sum(fn (UssdSession $s) => $s->contribution->amount_minor),
                'tickets_sent' => $sessions->where('outcome', UssdSession::OUTCOME_TICKETS_SENT)->count(),
            ],
            'funnel' => [
                ['label' => 'Dialed in', 'count' => $sessions->count()],
                ['label' => 'Chose an event', 'count' => $sessions->whereNotNull('event_id')->count()],
                ['label' => 'Placed an order', 'count' => $sessions->whereNotNull('order_id')->count()],
                ['label' => 'Paid', 'count' => $paidOrders->count()],
            ],
            'networks' => $sessions->groupBy(fn (UssdSession $s) => $s->network ?? 'Unknown')
                ->map(fn ($group, $network) => ['network' => $network, 'count' => $group->count()])
                ->sortByDesc('count')->values(),
            'daily' => collect(range($days === 1 ? 0 : min($days, 14) - 1, 0))->map(function (int $ago) use ($sessions) {
                $day = now()->subDays($ago)->toDateString();
                $onDay = $sessions->filter(fn (UssdSession $s) => $s->started_at->toDateString() === $day);

                return ['date' => $day, 'sessions' => $onDay->count(), 'paid' => $onDay->filter(fn (UssdSession $s) => $s->order?->status === 'paid')->count()];
            })->values(),
            'topEvents' => $sessions->whereNotNull('event_id')->groupBy('event_id')
                ->map(fn ($group, $eventId) => [
                    'event_id' => $eventId,
                    'sessions' => $group->count(),
                    'paid' => $group->filter(fn (UssdSession $s) => $s->order?->status === 'paid')->count(),
                ])
                ->sortByDesc('sessions')->take(5)->values()
                ->pipe(function ($rows) {
                    $events = Event::withTrashed()->whereIn('id', $rows->pluck('event_id'))->get(['id', 'title', 'ussd_code'])->keyBy('id');

                    return $rows->map(fn (array $row) => [
                        ...$row,
                        'title' => $events->get($row['event_id'])?->title ?? 'Deleted event',
                        'dial' => $events->get($row['event_id'])?->ussdDialString(),
                    ]);
                }),
            'recent' => UssdSession::with('event:id,title')->latest('started_at')->limit(25)->get()
                ->map(fn (UssdSession $s) => [
                    'id' => $s->id,
                    'caller' => $this->maskPhone($s->msisdn),
                    'network' => $s->network,
                    'event' => $s->event?->title,
                    'direct' => $s->dialed_direct,
                    'screens' => $s->screens,
                    'outcome' => $s->outcome,
                    'last_step' => $s->last_step,
                    'started_at' => $s->started_at->toIso8601String(),
                    'duration_seconds' => (int) $s->started_at->diffInSeconds($s->last_seen_at ?? $s->started_at),
                ]),
        ]);
    }

    /** 256770123456 becomes 25677****456: enough to tell callers apart, not to call them. */
    private function maskPhone(string $msisdn): string
    {
        return strlen($msisdn) > 7 ? substr($msisdn, 0, 5).str_repeat('*', strlen($msisdn) - 8).substr($msisdn, -3) : $msisdn;
    }
}
