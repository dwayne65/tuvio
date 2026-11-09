<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InsightsController extends Controller
{
    public function tickets(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $tickets = Ticket::query()
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
            ->when($request->string('event')->value(), fn ($q, $slug) => $q->whereHas('event', fn ($e) => $e->where('slug', $slug)))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->with(['event:id,title,slug', 'ticketType:id,name'])
            ->latest()
            ->paginate(15)->withQueryString()
            ->through(fn (Ticket $t) => [
                'code' => $t->code, 'status' => $t->status,
                'attendee_name' => $t->attendee_name, 'attendee_email' => $t->attendee_email,
                'event' => $t->event->title, 'type' => $t->ticketType->name,
                'issued_at' => $t->issued_at?->toIso8601String(),
                'used_at' => $t->used_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard/Tickets', [
            'tickets' => $tickets,
            'events' => $org->events()->orderBy('title')->get(['title', 'slug']),
            'filters' => ['event' => $request->string('event')->value(), 'status' => $request->string('status')->value()],
        ]);
    }

    public function attendees(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $base = Ticket::query()
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
            ->when($request->string('event')->value(), fn ($q, $slug) => $q->whereHas('event', fn ($e) => $e->where('slug', $slug)));

        $attendees = (clone $base)->with(['event:id,title', 'ticketType:id,name'])
            ->latest()->paginate(20)->withQueryString()
            ->through(fn (Ticket $t) => [
                'name' => $t->attendee_name, 'email' => $t->attendee_email,
                'event' => $t->event->title, 'type' => $t->ticketType->name,
                'checked_in' => $t->status === 'used', 'code' => $t->code,
            ]);

        return Inertia::render('Dashboard/Attendees', [
            'attendees' => $attendees,
            'summary' => [
                'total' => (clone $base)->count(),
                'checked_in' => (clone $base)->where('status', 'used')->count(),
            ],
            'events' => $org->events()->orderBy('title')->get(['title', 'slug']),
            'filters' => ['event' => $request->string('event')->value()],
        ]);
    }

    public function exportAttendees(Request $request): StreamedResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('view', $org);

        $rows = Ticket::query()
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
            ->when($request->string('event')->value(), fn ($q, $slug) => $q->whereHas('event', fn ($e) => $e->where('slug', $slug)))
            ->with(['event:id,title', 'ticketType:id,name'])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ticket Code', 'Attendee', 'Email', 'Event', 'Ticket Type', 'Status', 'Checked In At']);
            foreach ($rows as $t) {
                fputcsv($out, [$t->code, $t->attendee_name, $t->attendee_email, $t->event->title, $t->ticketType->name, $t->status, $t->used_at]);
            }
            fclose($out);
        }, 'attendees-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function transactions(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $payments = Payment::query()
            ->where(function ($q) use ($org) {
                $q->whereHas('order.event', fn ($e) => $e->where('organization_id', $org->id))
                    ->orWhereHas('contribution.campaign', fn ($c) => $c->where('organization_id', $org->id));
            })
            ->with(['order.event:id,title', 'contribution.campaign:id,title'])
            ->latest()->paginate(15)->withQueryString()
            ->through(fn (Payment $p) => [
                'reference' => $p->reference, 'purpose' => $p->purpose, 'provider' => $p->provider,
                'method' => $p->method, 'status' => $p->status,
                'amount_minor' => $p->amount_minor, 'currency' => $p->currency,
                'source' => $p->order?->event?->title ?? $p->contribution?->campaign?->title ?? '—',
                'at' => $p->created_at->toIso8601String(),
            ]);

        return Inertia::render('Dashboard/Transactions', ['transactions' => $payments]);
    }

    public function analytics(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $paidOrders = Order::query()
            ->where('status', 'paid')
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
            ->where('paid_at', '>=', now()->subDays(30))
            ->get(['id', 'event_id', 'total_minor', 'commission_minor', 'subtotal_minor', 'discount_minor', 'paid_at']);

        // Build a 30-day series (zero-filled).
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->format('Y-m-d'));
        $byDay = $paidOrders->groupBy(fn ($o) => Carbon::parse($o->paid_at)->format('Y-m-d'));
        $series = $days->map(fn ($day) => [
            'date' => $day,
            'revenue' => (int) ($byDay->get($day)?->sum('total_minor') ?? 0),
            'tickets' => (int) ($byDay->get($day)?->count() ?? 0),
        ]);

        $allPaid = Order::where('status', 'paid')->whereHas('event', fn ($q) => $q->where('organization_id', $org->id));

        $topEvents = $org->events()->withCount(['tickets as sold' => fn ($q) => $q->whereIn('status', ['issued', 'used'])])
            ->orderByDesc('sold')->limit(5)->get(['id', 'title'])
            ->map(fn ($e) => ['title' => $e->title, 'sold' => $e->sold]);

        return Inertia::render('Dashboard/Analytics', [
            'series' => $series,
            'totals' => [
                'gross_minor' => (int) (clone $allPaid)->sum('total_minor'),
                'net_minor' => (int) ((clone $allPaid)->sum('subtotal_minor') - (clone $allPaid)->sum('discount_minor') - (clone $allPaid)->sum('commission_minor')),
                'orders' => (clone $allPaid)->count(),
                'tickets' => Ticket::whereHas('event', fn ($q) => $q->where('organization_id', $org->id))->whereIn('status', ['issued', 'used'])->count(),
            ],
            'top_events' => $topEvents,
            'currency' => $org->default_currency,
        ]);
    }
}
