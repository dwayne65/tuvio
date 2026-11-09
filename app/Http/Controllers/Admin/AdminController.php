<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Payouts\PayoutService;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PayoutPaidNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $gmv = (int) Order::where('status', 'paid')->sum('total_minor');
        $commission = $this->platformBalance(ChartOfAccounts::COMMISSION_INCOME);

        // 30-day platform GMV series (zero-filled).
        $orders = Order::where('status', 'paid')->where('paid_at', '>=', now()->subDays(30))->get(['total_minor', 'paid_at']);
        $byDay = $orders->groupBy(fn ($o) => Carbon::parse($o->paid_at)->format('Y-m-d'));
        $series = collect(range(29, 0))->map(function ($d) use ($byDay) {
            $day = now()->subDays($d)->format('Y-m-d');

            return ['date' => $day, 'revenue' => (int) ($byDay->get($day)?->sum('total_minor') ?? 0)];
        });

        return Inertia::render('Admin/Dashboard', [
            'series' => $series,
            'metrics' => [
                'users' => User::count(),
                'organizers' => Organization::where('status', 'active')->count(),
                'events' => Event::where('status', 'published')->count(),
                'tickets_sold' => Ticket::whereIn('status', ['issued', 'used'])->count(),
                'gmv_minor' => $gmv,
                'platform_revenue_minor' => $commission,
                'contributions_minor' => (int) Campaign::sum('raised_minor'),
                'pending_payouts_minor' => (int) Payout::whereIn('status', ['requested', 'pending', 'processing'])->sum('amount_minor'),
            ],
            'recent_orders' => Order::where('status', 'paid')->with('event')->latest()->limit(8)->get()
                ->map(fn ($o) => ['reference' => $o->reference, 'event' => $o->event->title, 'total_minor' => $o->total_minor, 'currency' => $o->currency, 'at' => $o->paid_at?->toIso8601String()]),
        ]);
    }

    public function users(Request $request): Response
    {
        return Inertia::render('Admin/Users', [
            'users' => User::query()
                ->when($request->string('q')->value(), fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
                ->latest()->paginate(20)
                ->through(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'is_admin' => $u->is_admin, 'verified' => (bool) $u->email_verified_at, 'created_at' => $u->created_at->toIso8601String()]),
            'filters' => ['q' => $request->string('q')->value()],
        ]);
    }

    public function organizers(): Response
    {
        return Inertia::render('Admin/Organizers', [
            'organizations' => Organization::withCount('events')->latest()->paginate(20)
                ->through(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'slug' => $o->slug, 'status' => $o->status, 'verification_status' => $o->verification_status, 'events_count' => $o->events_count]),
        ]);
    }

    public function transactions(): Response
    {
        return Inertia::render('Admin/Transactions', [
            'payments' => Payment::latest()->paginate(20)
                ->through(fn ($p) => ['reference' => $p->reference, 'purpose' => $p->purpose, 'provider' => $p->provider, 'status' => $p->status, 'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'at' => $p->created_at->toIso8601String()]),
        ]);
    }

    public function payouts(): Response
    {
        return Inertia::render('Admin/Payouts', [
            'payouts' => Payout::with('organization')->latest()->paginate(20)
                ->through(fn ($p) => ['id' => $p->id, 'reference' => $p->reference, 'organization' => $p->organization->name, 'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'status' => $p->status, 'requested_at' => $p->requested_at?->toIso8601String()]),
        ]);
    }

    public function approvePayout(Request $request, Payout $payout, PayoutService $payouts): RedirectResponse
    {
        abort_unless(in_array($payout->status, ['requested', 'pending', 'processing'], true), 422);

        $payouts->markPaid($payout, $request->user()->id, $request->input('provider_ref'));

        if ($payout->organization->owner) {
            $payout->organization->owner->notify(new PayoutPaidNotification($payout));
        }

        return back()->with('success', "Payout {$payout->reference} marked as paid.");
    }

    private function platformBalance(string $code): int
    {
        $account = LedgerAccount::whereNull('owner_type')->where('code', $code)->first();

        return $account ? $account->balanceMinor() : 0;
    }
}
