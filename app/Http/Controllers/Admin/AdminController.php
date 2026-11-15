<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\SiteTraffic;
use App\Domain\Ledger\PlatformEarnings;
use App\Domain\Payouts\PayoutRejected;
use App\Domain\Payouts\PayoutService;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $gmv = (int) Order::where('status', 'paid')->sum('total_minor');
        $earnings = app(PlatformEarnings::class)->summary(config('ticketflow.default_currency'));

        // 30-day platform GMV series (zero-filled).
        $orders = Order::where('status', 'paid')->where('paid_at', '>=', now()->subDays(30))->get(['total_minor', 'paid_at']);
        $byDay = $orders->groupBy(fn ($o) => Carbon::parse($o->paid_at)->format('Y-m-d'));
        $series = collect(range(29, 0))->map(function ($d) use ($byDay) {
            $day = now()->subDays($d)->format('Y-m-d');

            return ['date' => $day, 'revenue' => (int) ($byDay->get($day)?->sum('total_minor') ?? 0)];
        });

        return Inertia::render('Admin/Dashboard', [
            'series' => $series,
            'traffic' => app(SiteTraffic::class)->summary(),
            'metrics' => [
                'users' => User::count(),
                'organizers' => Organization::where('status', 'active')->count(),
                'events' => Event::where('status', 'published')->count(),
                'tickets_sold' => Ticket::whereIn('status', ['issued', 'used'])->count(),
                'gmv_minor' => $gmv,
                'platform_revenue_minor' => $earnings['earned'],
                'commission_minor' => $earnings['commission'] + $earnings['service_fees'],
                'subscriptions_minor' => $earnings['subscriptions'],
                'available_minor' => $earnings['available'],
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

    public function payouts(PayoutService $payouts): Response
    {
        return Inertia::render('Admin/Payouts', [
            'driver' => config('ticketflow.payouts.driver'),
            'payouts' => Payout::with('organization', 'payoutAccount')->latest()->paginate(20)
                ->through(fn (Payout $p) => [
                    'id' => $p->id, 'reference' => $p->reference, 'organization' => $p->organization->name,
                    'amount_minor' => $p->amount_minor, 'currency' => $p->currency, 'status' => $p->status,
                    'provider' => $p->provider, 'provider_ref' => $p->provider_ref, 'notes' => $p->notes,
                    'automatic' => $payouts->canSendAutomatically($p),
                    // Admins need the full destination to pay manually or to check a transfer.
                    'account' => $p->payoutAccount ? [
                        'type' => $p->payoutAccount->type, 'provider' => $p->payoutAccount->provider,
                        'account_name' => $p->payoutAccount->account_name, 'account_number' => $p->payoutAccount->account_number,
                        'bank_code' => $p->payoutAccount->bank_code,
                    ] : null,
                    'requested_at' => $p->requested_at?->toIso8601String(),
                    'processed_at' => $p->processed_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Approves a requested payout: Mobile Money goes out through Paytota when that driver
     * is on, otherwise the admin has paid it by hand and records the transfer reference.
     */
    public function approvePayout(Request $request, Payout $payout, PayoutService $payouts): RedirectResponse
    {
        abort_unless($payout->status === 'requested', 422);
        $automatic = $payouts->canSendAutomatically($payout);

        $data = $request->validate([
            'provider_ref' => [$automatic ? 'nullable' : 'required', 'string', 'max:120'],
            // Moving money out needs the password again, even inside a verified session.
            'password' => ['required', 'current_password'],
        ]);

        try {
            $payout = $automatic
                ? $payouts->send($payout, $request->user()->id)
                : $payouts->markPaid($payout, $request->user()->id, $data['provider_ref']);
        } catch (PayoutRejected $e) {
            return back()->with('error', "Paytota refused payout {$payout->reference}: {$e->getMessage()}");
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $this->audit($request, $payout->fresh(), $automatic ? 'admin.payout_sent' : 'admin.payout_marked_paid');
        }

        return back()->with('success', match ($payout->status) {
            'paid' => "Payout {$payout->reference} is paid.",
            'failed' => "Payout {$payout->reference} was declined: {$payout->notes}",
            default => "Payout {$payout->reference} sent to Paytota. It will be marked paid when the transfer is confirmed.",
        });
    }

    public function refreshPayout(Payout $payout, PayoutService $payouts): RedirectResponse
    {
        try {
            $payout = $payouts->refreshStatus($payout);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout {$payout->reference} is {$payout->status}.");
    }

    /** Declines a requested payout and returns its funds to the organizer's balance. */
    public function rejectPayout(Request $request, Payout $payout, PayoutService $payouts): RedirectResponse
    {
        abort_unless($payout->status === 'requested', 422);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $payout = $payouts->markFailed($payout, $data['reason']);
        $this->audit($request, $payout, 'admin.payout_rejected');

        return back()->with('success', "Payout {$payout->reference} rejected; the funds are back in the organizer's balance.");
    }

    private function audit(Request $request, Payout $payout, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => Payout::class,
            'auditable_id' => $payout->id,
            'new_values' => ['status' => $payout->status, 'amount_minor' => $payout->amount_minor, 'provider_ref' => $payout->provider_ref, 'notes' => $payout->notes],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
