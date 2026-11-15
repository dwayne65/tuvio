<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Platform reports: sales by event, organizer and promoter, plus sign-ups, over a chosen period. */
class ReportController extends Controller
{
    /** @var list<int> */
    private const PERIOD_DAYS = [7, 30, 90, 365];

    /** @var list<string> */
    private const REPORTS = ['events', 'organizers', 'promoters'];

    public function index(Request $request): Response
    {
        $days = $this->days($request);
        $since = now()->subDays($days)->startOfDay();

        return Inertia::render('Admin/Reports', [
            'days' => $days,
            'periods' => self::PERIOD_DAYS,
            'since' => $since->toIso8601String(),
            'summary' => [
                'orders' => $this->paidOrders($since)->count(),
                'revenue' => $this->revenueByCurrency($since),
                'new_users' => User::where('created_at', '>=', $since)->count(),
                'new_organizers' => Organization::where('created_at', '>=', $since)->count(),
                'live_events' => Event::where('status', 'published')->count(),
            ],
            'events' => $this->eventRows($since)->take(15)->values(),
            'organizers' => $this->organizerRows($since)->take(15)->values(),
            'promoters' => $this->promoterRows($since)->take(15)->values(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = in_array($request->string('report')->value(), self::REPORTS, true)
            ? $request->string('report')->value()
            : 'events';
        $since = now()->subDays($this->days($request))->startOfDay();

        [$headers, $rows] = match ($report) {
            'organizers' => [
                ['Organizer', 'Currency', 'Paid orders', 'Revenue (minor units)'],
                $this->organizerRows($since)->map(fn (array $row) => [$row['name'], $row['currency'], $row['orders'], $row['revenue_minor']]),
            ],
            'promoters' => [
                ['Promoter', 'Code', 'Organizer', 'Currency', 'Paid orders', 'Revenue (minor units)'],
                $this->promoterRows($since)->map(fn (array $row) => [$row['name'], $row['code'], $row['organization'], $row['currency'], $row['orders'], $row['revenue_minor']]),
            ],
            default => [
                ['Event', 'Organizer', 'Currency', 'Paid orders', 'Revenue (minor units)'],
                $this->eventRows($since)->map(fn (array $row) => [$row['title'], $row['organization'], $row['currency'], $row['orders'], $row['revenue_minor']]),
            ],
        };

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, "tuvio-{$report}-report-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function days(Request $request): int
    {
        $days = (int) $request->input('days', 30);

        return in_array($days, self::PERIOD_DAYS, true) ? $days : 30;
    }

    /** @return Builder<Order> */
    private function paidOrders(Carbon $since): Builder
    {
        return Order::query()->where('orders.status', 'paid')->where('orders.paid_at', '>=', $since);
    }

    /** @return array<int, array{currency: string, minor: int}> */
    private function revenueByCurrency(Carbon $since): array
    {
        return $this->paidOrders($since)
            ->selectRaw('currency, sum(total_minor) as minor')
            ->groupBy('currency')
            ->get()
            ->map(fn (Order $order) => ['currency' => $order->currency, 'minor' => (int) $order->minor])
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function eventRows(Carbon $since): Collection
    {
        $rows = $this->paidOrders($since)
            ->selectRaw('event_id, currency, count(*) as orders_count, sum(total_minor) as revenue_minor')
            ->groupBy('event_id', 'currency')
            ->orderByDesc('revenue_minor')
            ->get();

        $events = Event::withTrashed()->with('organization:id,name')
            ->whereIn('id', $rows->pluck('event_id'))->get()->keyBy('id');

        return $rows->map(fn (Order $row) => [
            'id' => $row->event_id,
            'title' => $events[$row->event_id]?->title ?? 'Unknown event',
            'organization' => $events[$row->event_id]?->organization?->name,
            'currency' => $row->currency,
            'orders' => (int) $row->orders_count,
            'revenue_minor' => (int) $row->revenue_minor,
        ])->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function organizerRows(Carbon $since): Collection
    {
        $rows = $this->paidOrders($since)
            ->join('events', 'events.id', '=', 'orders.event_id')
            ->selectRaw('events.organization_id, orders.currency, count(orders.id) as orders_count, sum(orders.total_minor) as revenue_minor')
            ->groupBy('events.organization_id', 'orders.currency')
            ->orderByDesc('revenue_minor')
            ->get();

        $organizations = Organization::whereIn('id', $rows->pluck('organization_id'))->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn (Order $row) => [
            'id' => $row->organization_id,
            'name' => $organizations[$row->organization_id]?->name ?? 'Unknown organizer',
            'currency' => $row->currency,
            'orders' => (int) $row->orders_count,
            'revenue_minor' => (int) $row->revenue_minor,
        ])->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function promoterRows(Carbon $since): Collection
    {
        $rows = $this->paidOrders($since)
            ->whereNotNull('promoter_id')
            ->selectRaw('promoter_id, currency, count(*) as orders_count, sum(total_minor) as revenue_minor')
            ->groupBy('promoter_id', 'currency')
            ->orderByDesc('revenue_minor')
            ->get();

        $promoters = Promoter::with('organization:id,name')->whereIn('id', $rows->pluck('promoter_id'))->get()->keyBy('id');

        return $rows->map(fn (Order $row) => [
            'id' => $row->promoter_id,
            'name' => $promoters[$row->promoter_id]?->name ?? 'Removed promoter',
            'code' => $promoters[$row->promoter_id]?->code,
            'organization' => $promoters[$row->promoter_id]?->organization?->name,
            'currency' => $row->currency,
            'orders' => (int) $row->orders_count,
            'revenue_minor' => (int) $row->revenue_minor,
        ])->values();
    }
}
