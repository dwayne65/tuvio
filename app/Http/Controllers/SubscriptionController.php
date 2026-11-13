<?php

namespace App\Http\Controllers;

use App\Domain\Payments\MobileMoneyPromptFailed;
use App\Domain\Payments\PaymentService;
use App\Domain\Subscriptions\SubscriptionService;
use App\Models\Organization;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private PaymentService $payments,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }

        $this->authorize('view', $org);

        $plan = $org->currentPlan();
        $active = $org->activeSubscription();
        $pendingInvoice = $this->pendingInvoice($org);
        $seats = $this->subscriptions->teamSeats($org);

        return Inertia::render('Dashboard/Subscription', [
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort')->get()->map(fn (SubscriptionPlan $p) => self::planProps($p)),
            'capabilities' => SubscriptionPlan::CAPABILITIES,
            'current' => [
                'plan_id' => $plan?->id,
                'plan_name' => $plan?->name ?? 'No plan',
                'commission_bps' => $org->commissionBps(),
                'status' => $active?->status,
                'is_paid' => $active !== null && ! $active->plan->isFree(),
                'current_period_end' => $active?->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => (bool) $active?->cancel_at_period_end,
            ],
            'usage' => [
                'events' => $this->subscriptions->eventsCountedAgainstLimit($org),
                'event_limit' => $plan?->event_limit,
                'team' => $seats['used'],
                'team_limit' => $seats['limit'],
            ],
            'pending_invoice' => $pendingInvoice ? [
                'number' => $pendingInvoice->number,
                'plan_name' => $pendingInvoice->subscription->plan->name,
                'amount_minor' => $pendingInvoice->amount_minor,
                'currency' => $pendingInvoice->currency,
            ] : null,
            'invoices' => SubscriptionInvoice::whereIn('subscription_id', $org->subscription()->select('id'))
                ->where('status', 'paid')->with('subscription.plan')->latest('paid_at')->limit(12)->get()
                ->map(fn (SubscriptionInvoice $invoice) => [
                    'number' => $invoice->number,
                    'plan_name' => $invoice->subscription->plan->name,
                    'amount_minor' => $invoice->amount_minor,
                    'currency' => $invoice->currency,
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                    'period_end' => $invoice->period_end?->toIso8601String(),
                ]),
            'can_manage' => $request->user()->can('manageFinances', $org),
            'payment_methods' => $this->availablePaymentMethods(),
            'default_phone' => $request->user()->phone ?? $org->support_phone ?? '',
        ]);
    }

    /**
     * Choose a plan. The free plan applies at once; a paid plan sends the organizer to the
     * payment provider and is only switched on once that payment is verified.
     */
    public function store(Request $request): SymfonyResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id'], ...$this->paymentRules()], $this->paymentMessages());
        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']);

        if ($plan->isFree()) {
            $subscription = $this->subscriptions->subscribeToFree($org, $plan);

            return redirect()->route('subscription.index')->with('success', $subscription->cancel_at_period_end
                ? "Your paid plan stays active until {$subscription->current_period_end->toFormattedDateString()}, then you move to {$plan->name}."
                : "You're on the {$plan->name} plan.");
        }

        return $this->pay($this->subscriptions->checkout($org, $plan), $data);
    }

    /** Pay an invoice that is still open, e.g. after abandoning the provider's checkout. */
    public function payInvoice(Request $request, string $number): SymfonyResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $data = $request->validate($this->paymentRules(), $this->paymentMessages());
        $invoice = $this->invoiceOf($org, $number);
        if ($invoice->status !== 'open') {
            return redirect()->route('subscription.index')->with('error', 'This invoice is no longer open for payment.');
        }

        return $this->pay($invoice, $data);
    }

    /** "Approve on your phone" page; reloads itself until the Mobile Money payment settles. */
    public function approve(Request $request, string $number): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);

        $invoice = $this->invoiceOf($org, $number);
        $payment = $invoice->payments()->latest('id')->first();

        if ($invoice->status === 'paid') {
            return redirect()->route('subscription.index')->with('success', "Payment received. Your {$invoice->subscription->plan->name} plan is now active.");
        }

        if (! $payment || $invoice->status !== 'open') {
            return redirect()->route('subscription.index')->with('error', 'This payment is no longer open. Please start again.');
        }

        if ($payment->status === 'pending') {
            try {
                if ($this->payments->verifyAndFulfill($payment)) {
                    return redirect()->route('subscription.index')->with('success', "Payment received. Your {$invoice->subscription->plan->name} plan is now active.");
                }
            } catch (RuntimeException $e) {
                report($e);

                return redirect()->route('subscription.index')->with('error', 'We could not confirm this payment. Please contact support with invoice '.$invoice->number.'.');
            }
        }

        if ($payment->fresh()->status === 'failed') {
            return redirect()->route('subscription.index')->with('error', $this->mobileMoneyFailureMessage());
        }

        return Inertia::render('Dashboard/SubscriptionApproval', [
            'invoice' => [
                'number' => $invoice->number,
                'plan_name' => $invoice->subscription->plan->name,
                'amount_minor' => $invoice->amount_minor,
                'currency' => $invoice->currency,
                'phone_hint' => Str::mask((string) preg_replace('/\D/', '', (string) $invoice->payer_phone), '•', 0, -4),
            ],
        ]);
    }

    /** Provider return URL: verify the payment server-side before anything is switched on. */
    public function callback(Request $request, string $number): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);

        $invoice = $this->invoiceOf($org, $number);
        $payment = $invoice->payments()->latest('id')->first();

        try {
            $paid = $payment && $this->payments->verifyAndFulfill($payment);
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route('subscription.index')->with('error', 'We could not confirm this payment. Please contact support with invoice '.$invoice->number.'.');
        }

        return $paid
            ? redirect()->route('subscription.index')->with('success', "Payment received. Your {$invoice->subscription->plan->name} plan is now active.")
            : redirect()->route('subscription.index')->with('error', 'The payment was not completed, so your plan has not changed. You can try again below.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $subscription = $org->activeSubscription();
        abort_unless($subscription && ! $subscription->plan->isFree(), 404);
        $this->subscriptions->cancel($subscription, atPeriodEnd: true);

        return back()->with('success', 'Your plan will end at the close of your billing period.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $subscription = $org->activeSubscription();
        abort_unless($subscription, 404);
        $this->subscriptions->resume($subscription);

        return back()->with('success', 'Your plan will renew as normal.');
    }

    /** @return array<string, mixed> */
    public static function planProps(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id, 'name' => $plan->name, 'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => ['minor' => $plan->price_minor, 'currency' => $plan->currency],
            'commission_bps' => $plan->commission_bps ?? (int) config('ticketflow.commission_bps'),
            'interval' => $plan->interval,
            'event_limit' => $plan->event_limit,
            'lifetime_event_limit' => $plan->lifetime_event_limit,
            'ticket_limit' => $plan->ticket_limit,
            'team_member_limit' => $plan->team_member_limit,
            'ussd_event_limit' => $plan->ussd_event_limit,
            'capabilities' => array_values($plan->capabilities ?? []),
            'features' => $plan->features ?? [],
        ];
    }

    /**
     * Start paying an invoice. Mobile Money sends a PIN prompt to the given phone and waits on
     * the approval page; card goes to the provider's hosted checkout.
     *
     * @param  array{payment_method?: ?string, phone?: ?string}  $data
     */
    private function pay(SubscriptionInvoice $invoice, array $data): SymfonyResponse
    {
        $method = $data['payment_method'] ?? null;
        if ($method === 'mobile_money') {
            $invoice->update(['payer_phone' => $data['phone']]);
        }

        try {
            $intent = $this->payments->initiateForSubscriptionInvoice($invoice, route('subscription.callback', $invoice->number), method: $method);
        } catch (MobileMoneyPromptFailed $e) {
            report($e);

            // Record why Paytota refused, so a retry starts from a settled payment.
            $this->payments->verifyAndFulfill($invoice->payments()->latest('id')->firstOrFail());

            return redirect()->route('subscription.index')->withErrors(['phone' => $this->mobileMoneyFailureMessage()]);
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route('subscription.index')->with('error', 'We could not reach the payment provider. Please try again in a moment.');
        }

        // Demo mode settles the fake provider at once, as checkout does.
        if (config('ticketflow.demo_mode') && config('ticketflow.payments.default') === 'fake') {
            return $this->callback(request(), $invoice->number);
        }

        if ($intent->meta['awaiting_phone_approval'] ?? false) {
            return redirect()->route('subscription.approve', $invoice->number);
        }

        return Inertia::location($intent->redirectUrl);
    }

    /** @return array<string, array<int, mixed>> */
    private function paymentRules(): array
    {
        return [
            'payment_method' => ['nullable', Rule::in($this->availablePaymentMethods())],
            'phone' => ['nullable', 'required_if:payment_method,mobile_money', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s-]{8,19}$/'],
        ];
    }

    /** @return array<string, string> */
    private function paymentMessages(): array
    {
        return [
            'phone.required_if' => 'Enter the Mobile Money number to send the payment prompt to.',
            'phone.regex' => 'Enter a valid phone number, e.g. 0770 123456 or +256 770 123456.',
        ];
    }

    /**
     * Mobile Money is only offered when the provider can route it, as at ticket checkout.
     *
     * @return list<string>
     */
    private function availablePaymentMethods(): array
    {
        $mobileMoneyEnabled = config('ticketflow.payments.default') !== 'paytota'
            || config('ticketflow.payments.paytota.mobile_money');

        return $mobileMoneyEnabled ? ['mobile_money', 'card'] : ['card'];
    }

    private function mobileMoneyFailureMessage(): string
    {
        return 'The Mobile Money payment was declined, cancelled or timed out. Check the number and balance, then try again, or pay by card.';
    }

    private function pendingInvoice(Organization $org): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::where('status', 'open')
            ->whereIn('subscription_id', $org->subscription()->where('status', 'incomplete')->select('id'))
            ->with('subscription.plan')
            ->latest('id')
            ->first();
    }

    /** An invoice of this organization; another organization's invoice is reported as missing. */
    private function invoiceOf(Organization $org, string $number): SubscriptionInvoice
    {
        return SubscriptionInvoice::where('number', $number)
            ->whereIn('subscription_id', $org->subscription()->select('id'))
            ->with('subscription.plan')
            ->firstOrFail();
    }
}
