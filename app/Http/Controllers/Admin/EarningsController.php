<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\PlatformEarnings;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformWithdrawal;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** The platform's own money: commission, booking fees and plan payments, and withdrawing them. */
class EarningsController extends Controller
{
    public function __construct(private PlatformEarnings $earnings) {}

    public function index(): Response
    {
        $currency = config('ticketflow.default_currency');

        return Inertia::render('Admin/Earnings', [
            'currency' => $currency,
            'summary' => $this->earnings->summary($currency),
            'monthly' => $this->earnings->monthly($currency),
            'taxLabel' => config('ticketflow.tax.label'),
            'withdrawals' => PlatformWithdrawal::with('creator')->latest('paid_at')->limit(20)->get()
                ->map(fn (PlatformWithdrawal $w) => [
                    'reference' => $w->reference,
                    'amount_minor' => $w->amount_minor,
                    'currency' => $w->currency,
                    'method' => $w->method,
                    'destination' => $w->destination,
                    'provider_ref' => $w->provider_ref,
                    'note' => $w->note,
                    'by' => $w->creator?->name,
                    'paid_at' => $w->paid_at->toIso8601String(),
                ]),
            'planPayments' => SubscriptionInvoice::where('status', 'paid')->with('subscription.organization', 'subscription.plan')
                ->latest('paid_at')->limit(10)->get()
                ->map(fn (SubscriptionInvoice $invoice) => [
                    'number' => $invoice->number,
                    'organization' => $invoice->subscription->organization?->name,
                    'plan' => $invoice->subscription->plan->name,
                    'amount_minor' => $invoice->amount_minor,
                    'currency' => $invoice->currency,
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                ]),
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', Rule::in(['mobile_money', 'bank'])],
            'destination' => ['required', 'string', 'max:120'],
            'provider_ref' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            // Moving money out needs the password again, even inside a verified session.
            'password' => ['required', 'current_password'],
        ]);

        try {
            $withdrawal = $this->earnings->withdraw($request->user(), config('ticketflow.default_currency'), [
                'amount_minor' => (int) round($data['amount'] * 100),
                'method' => $data['method'],
                'destination' => $data['destination'],
                'provider_ref' => $data['provider_ref'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.platform_withdrawal',
            'auditable_type' => PlatformWithdrawal::class,
            'auditable_id' => $withdrawal->id,
            'new_values' => ['amount_minor' => $withdrawal->amount_minor, 'method' => $withdrawal->method, 'destination' => $withdrawal->destination],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        return back()->with('success', "Withdrawal {$withdrawal->reference} recorded.");
    }
}
