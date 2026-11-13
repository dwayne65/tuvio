<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Ticket;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sales and attendance reports, per event and per ticket type. */
class ReportController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $rows = $this->eventRows($org);
        $selected = $request->string('event')->value()
            ? $org->events()->where('slug', $request->string('event')->value())->first()
            : null;

        return Inertia::render('Dashboard/Reports', [
            'rows' => $rows,
            'totals' => [
                'sold' => $rows->sum('sold'),
                'checked_in' => $rows->sum('checked_in'),
                'orders' => $rows->sum('orders'),
                'gross_minor' => $rows->sum('gross_minor'),
                'discount_minor' => $rows->sum('discount_minor'),
                'net_minor' => $rows->sum('net_minor'),
            ],
            'selected' => $selected ? [
                'title' => $selected->title,
                'slug' => $selected->slug,
                'types' => $this->ticketTypeRows($selected),
            ] : null,
            'currency' => $org->default_currency,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('view', $org);

        $rows = $this->eventRows($org);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'Date', 'Status', 'Orders', 'Tickets sold', 'Checked in', 'Attendance %', 'Currency', 'Gross', 'Discounts', 'Commission', 'Net']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['title'], $r['start_at'], $r['status'], $r['orders'], $r['sold'], $r['checked_in'],
                    $r['attendance'] ?? '', $r['currency'],
                    $r['gross_minor'] / 100, $r['discount_minor'] / 100, $r['commission_minor'] / 100, $r['net_minor'] / 100,
                ]);
            }
            fclose($out);
        }, 'sales-report-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function eventRows(Organization $org): Collection
    {
        $paid = fn ($q) => $q->where('status', 'paid');

        return $org->events()
            ->withCount([
                'tickets as sold' => fn ($q) => $q->whereIn('status', ['issued', 'used']),
                'tickets as checked_in' => fn ($q) => $q->where('status', 'used'),
                'orders as orders_count' => $paid,
            ])
            ->withSum(['orders as gross_minor' => $paid], 'total_minor')
            ->withSum(['orders as subtotal_minor' => $paid], 'subtotal_minor')
            ->withSum(['orders as discount_minor' => $paid], 'discount_minor')
            ->withSum(['orders as commission_minor' => $paid], 'commission_minor')
            // Tax on the fee is the organizer's only where they absorbed the fee as commission.
            ->withSum(['orders as organizer_tax_minor' => fn ($q) => $paid($q)->where('commission_minor', '>', 0)], 'tax_minor')
            ->orderByDesc('start_at')
            ->get()
            ->map(fn (Event $e) => [
                'title' => $e->title,
                'slug' => $e->slug,
                'status' => $e->status,
                'start_at' => $e->start_at?->toIso8601String(),
                'currency' => $e->currency,
                'orders' => $e->orders_count,
                'sold' => $e->sold,
                'checked_in' => $e->checked_in,
                'attendance' => $e->sold > 0 ? round($e->checked_in / $e->sold * 100, 1) : null,
                'gross_minor' => (int) $e->gross_minor,
                'discount_minor' => (int) $e->discount_minor,
                'commission_minor' => (int) $e->commission_minor + (int) $e->organizer_tax_minor,
                'net_minor' => (int) $e->subtotal_minor - (int) $e->discount_minor - (int) $e->commission_minor - (int) $e->organizer_tax_minor,
            ]);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function ticketTypeRows(Event $event): Collection
    {
        $tickets = Ticket::where('event_id', $event->id)
            ->selectRaw("ticket_type_id, sum(case when status in ('issued','used') then 1 else 0 end) as sold, sum(case when status = 'used' then 1 else 0 end) as checked_in")
            ->groupBy('ticket_type_id')->get()->keyBy('ticket_type_id');

        $revenue = OrderItem::whereHas('order', fn ($q) => $q->where('event_id', $event->id)->where('status', 'paid'))
            ->selectRaw('ticket_type_id, sum(line_total_minor) as revenue')
            ->groupBy('ticket_type_id')->pluck('revenue', 'ticket_type_id');

        return $event->ticketTypes()->orderBy('sort')->get()->map(function ($t) use ($tickets, $revenue) {
            $sold = (int) ($tickets[$t->id]->sold ?? 0);
            $in = (int) ($tickets[$t->id]->checked_in ?? 0);

            return [
                'name' => $t->name,
                'capacity' => $t->quantity_total,
                'sold' => $sold,
                'checked_in' => $in,
                'attendance' => $sold > 0 ? round($in / $sold * 100, 1) : null,
                'revenue_minor' => (int) ($revenue[$t->id] ?? 0),
            ];
        });
    }
}
