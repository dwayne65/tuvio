<?php

namespace App\Http\Controllers;

use App\Domain\Orders\Discounts;
use App\Domain\Orders\OrderService;
use App\Domain\Payments\MobileMoneyPromptFailed;
use App\Domain\Payments\PaymentService;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Promoter;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    private const PAYMENT_METHODS = ['mobile_money', 'card'];

    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private Discounts $discounts,
    ) {}

    /** Entry from an event page: stash the selected tickets and go to checkout. */
    public function start(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $request->session()->put('checkout', [
            'event_id' => $event->id,
            'items' => $data['items'],
        ]);
        $request->session()->forget('checkout_order');

        return redirect()->route('checkout.show');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $checkout = $request->session()->get('checkout');
        if (! $checkout) {
            // Reloading checkout after the cart became an order: show that order's payment options.
            $heldOrder = $this->heldOrder($request);

            return $heldOrder ? $this->showHeldOrder($heldOrder) : redirect()->route('events.index');
        }

        $event = Event::findOrFail($checkout['event_id']);
        $types = TicketType::whereIn('id', collect($checkout['items'])->pluck('ticket_type_id'))->get()->keyBy('id');

        $lines = [];
        $subtotal = 0;
        foreach ($checkout['items'] as $item) {
            $type = $types[$item['ticket_type_id']] ?? null;
            if (! $type) {
                continue;
            }
            $lineTotal = $type->price_minor * $item['quantity'];
            $subtotal += $lineTotal;
            $lines[] = [
                'name' => $type->name, 'quantity' => $item['quantity'],
                'unit_price_minor' => $type->price_minor, 'line_total_minor' => $lineTotal,
            ];
        }

        // Re-check the applied code on every view: it may have expired or run out.
        $promo = null;
        $discount = 0;
        if ($code = $checkout['promo'] ?? null) {
            try {
                $promo = $this->discounts->resolve($event, $code);
                $discount = $this->discounts->amount($promo, $subtotal, $event->currency);
            } catch (RuntimeException $e) {
                $request->session()->forget('checkout.promo');
                $promo = null;
            }
        }

        $user = $request->user();
        $fees = $event->platformFee($subtotal - $discount);
        $fee = $fees['fee_minor'];
        $tax = $event->passesFeeToBuyer() ? $fees['tax_minor'] : 0;

        return Inertia::render('Checkout/Index', [
            'event' => ['title' => $event->title, 'slug' => $event->slug, 'currency' => $event->currency],
            'lines' => $lines,
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'fee_minor' => $fee,
            'tax_minor' => $tax,
            'tax_label' => config('ticketflow.tax.label'),
            'total_minor' => $subtotal - $discount + $fee + $tax,
            'promo' => $promo?->code,
            'buyer' => ['name' => $user?->name ?? '', 'email' => $user?->email ?? '', 'phone' => $user?->phone ?? ''],
            'payment_methods' => $this->availablePaymentMethods(),
        ]);
    }

    /** Validate a promo code against the current cart and remember it. */
    public function applyPromo(Request $request): RedirectResponse
    {
        $checkout = $request->session()->get('checkout');
        if (! $checkout) {
            return redirect()->route('events.index');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $event = Event::findOrFail($checkout['event_id']);
        $subtotal = TicketType::whereIn('id', collect($checkout['items'])->pluck('ticket_type_id'))->get()
            ->sum(fn ($t) => $t->price_minor * (int) collect($checkout['items'])->firstWhere('ticket_type_id', $t->id)['quantity']);

        try {
            $promo = $this->discounts->resolve($event, $data['code']);
            $this->discounts->amount($promo, $subtotal, $event->currency);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $this->customerMessage($e)]);
        }

        $request->session()->put('checkout.promo', $promo->code);

        return back()->with('success', "Promo code {$promo->code} applied.");
    }

    public function removePromo(Request $request): RedirectResponse
    {
        $request->session()->forget('checkout.promo');

        return back();
    }

    public function store(Request $request): SymfonyResponse
    {
        $checkout = $request->session()->get('checkout');
        $heldOrder = $checkout ? null : $this->heldOrder($request);
        if (! $checkout && ! $heldOrder) {
            return redirect()->route('events.index')->with('error', 'Your checkout has expired. Please choose your tickets again.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'required_if:payment_method,mobile_money', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s-]{8,19}$/'],
            'payment_method' => ['nullable', Rule::in($this->availablePaymentMethods())],
        ], [
            'phone.required_if' => 'Enter the Mobile Money number to send the payment prompt to.',
            'phone.regex' => 'Enter a valid phone number, e.g. 0770 123456 or +256 770 123456.',
        ]);
        $buyer = Arr::only($validated, ['name', 'email', 'phone']);

        // Paying again from the checkout page (e.g. after going back from a failed attempt):
        // the cart became an order, so pay that order with the details just entered.
        if ($heldOrder) {
            $heldOrder->update(['buyer_name' => $buyer['name'], 'buyer_email' => $buyer['email'], 'buyer_phone' => $buyer['phone'] ?? null]);

            return $this->startPayment($heldOrder, $validated['payment_method'] ?? null);
        }

        $event = Event::findOrFail($checkout['event_id']);

        try {
            $order = $this->orders->create(
                $event, $buyer, $checkout['items'], $request->user(),
                promoCode: $checkout['promo'] ?? null,
                promoter: $this->referringPromoter($request, $event),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $this->customerMessage($e));
        }

        $request->session()->forget('checkout');
        $request->session()->put('checkout_order', $order->reference);

        // Free order: issue immediately, no payment.
        if ($order->total_minor <= 0) {
            $this->payments->fulfillFree($order);

            return redirect()->route('checkout.complete', $order->reference);
        }

        return $this->startPayment($order, $validated['payment_method'] ?? null);
    }

    /** Let the buyer try paying again (optionally by another method) while their tickets are held. */
    public function retryPayment(Request $request, string $reference): SymfonyResponse
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $method = $request->validate(['payment_method' => ['nullable', Rule::in($this->availablePaymentMethods())]])['payment_method']
            ?? $order->payments()->latest('id')->value('method');

        // A method switched off since the last attempt (e.g. Mobile Money) falls back to card.
        if ($method !== null && ! in_array($method, $this->availablePaymentMethods(), true)) {
            $method = 'card';
        }

        if (! $this->canRetryPayment($order)) {
            return redirect()->route('checkout.complete', $reference)
                ->with('error', 'This order can no longer be paid. Please start a new order.');
        }

        if ($method === 'mobile_money' && ! $order->buyer_phone) {
            return redirect()->route('checkout.complete', $reference)
                ->with('error', 'Add a phone number to pay with Mobile Money, or pay by card.');
        }

        return $this->startPayment($order, $method);
    }

    /** "Approve on your phone" page; reloads itself until the Mobile Money payment settles. */
    public function approve(string $reference): Response|RedirectResponse
    {
        $order = Order::where('reference', $reference)->with('event')->firstOrFail();
        $payment = $order->payments()->latest('id')->first();

        if ($order->status === 'paid') {
            return redirect()->route('checkout.complete', $reference)->with('success', 'Payment confirmed!');
        }

        if (! $payment || ! $this->canRetryPayment($order)) {
            return redirect()->route('checkout.complete', $reference)->with('error', 'This payment has expired. Please start a new order.');
        }

        if ($payment->status === 'pending') {
            try {
                if ($this->payments->verifyAndFulfill($payment)) {
                    return redirect()->route('checkout.complete', $reference)->with('success', 'Payment confirmed!');
                }
            } catch (RuntimeException $e) {
                return redirect()->route('checkout.complete', $reference)->with('error', $this->customerMessage($e));
            }
        }

        if ($payment->fresh()->status === 'failed') {
            return $this->backToCheckout($order, $this->mobileMoneyFailureMessage($payment->fresh()));
        }

        return Inertia::render('Checkout/AwaitingApproval', [
            'order' => [
                'reference' => $order->reference,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'event_title' => $order->event->title,
                'phone_hint' => Str::mask((string) preg_replace('/\D/', '', (string) $order->buyer_phone), '•', 0, -4),
                'reserved_until' => $order->reserved_until?->toIso8601String(),
            ],
        ]);
    }

    private function startPayment(Order $order, ?string $method = null): SymfonyResponse
    {
        try {
            $intent = $this->payments->initiateForOrder($order, route('checkout.callback', $order->reference), method: $method);
        } catch (MobileMoneyPromptFailed $e) {
            report($e);

            // Paytota has usually cancelled the purchase already; record why so the buyer gets a precise reason.
            $payment = $order->payments()->latest('id')->firstOrFail();
            $this->payments->verifyAndFulfill($payment);

            return $this->backToCheckout($order, $this->mobileMoneyFailureMessage($payment->fresh()));
        } catch (RuntimeException $e) {
            report($e);
            $message = 'We could not reach the payment provider. Your tickets are still held, so please try again.';

            return $method === 'mobile_money'
                ? $this->backToCheckout($order, $message)
                : redirect()->route('checkout.complete', $order->reference)->with('error', $message);
        }

        // Demo mode should behave like a completed hosted checkout without
        // requiring the browser to follow a fake provider redirect.
        if (config('ticketflow.demo_mode') && config('ticketflow.payments.default') === 'fake') {
            try {
                $this->payments->verifyAndFulfill($order->payments()->latest()->firstOrFail());
            } catch (RuntimeException $e) {
                return redirect()->route('checkout.complete', $order->reference)->with('error', $this->customerMessage($e));
            }

            return redirect()->route('checkout.complete', $order->reference)->with('success', 'Demo payment confirmed!');
        }

        // Mobile Money: the PIN prompt is already on the buyer's phone; wait for it here.
        if ($intent->meta['awaiting_phone_approval'] ?? false) {
            return redirect()->route('checkout.approve', $order->reference);
        }

        // Full-page redirect to the provider's hosted checkout. For Inertia visits this
        // is a 409 + X-Inertia-Location response, hence the Symfony return type.
        return Inertia::location($intent->redirectUrl);
    }

    /**
     * Mobile Money is only offered when the provider can actually route it.
     *
     * @return list<string>
     */
    private function availablePaymentMethods(): array
    {
        $mobileMoneyEnabled = config('ticketflow.payments.default') !== 'paytota'
            || config('ticketflow.payments.paytota.mobile_money');

        return $mobileMoneyEnabled ? self::PAYMENT_METHODS : ['card'];
    }

    private function mobileMoneyFailureMessage(Payment $payment): string
    {
        $attempts = $payment->provider_payload['transaction_data']['attempts'] ?? [];
        $errorCode = collect($attempts)->last()['error']['code'] ?? null;

        return $errorCode === 'no_matching_terminal'
            ? 'Mobile Money is not available right now. Please try again later, or choose another payment method.'
            : 'The Mobile Money payment was declined, cancelled or timed out. Check the number and try again, or choose another payment method.';
    }

    private function canRetryPayment(Order $order): bool
    {
        return $order->status === 'awaiting_payment'
            && $order->total_minor > 0
            && $order->reserved_until?->isFuture();
    }

    /** The checkout page for an order that is placed but not paid yet; its prices are fixed. */
    private function showHeldOrder(Order $order): Response
    {
        $order->load(['event', 'items', 'promoCode']);

        return Inertia::render('Checkout/Index', [
            'event' => ['title' => $order->event->title, 'slug' => $order->event->slug, 'currency' => $order->currency],
            'lines' => $order->items->map(fn ($item) => [
                'name' => $item->description, 'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor, 'line_total_minor' => $item->line_total_minor,
            ]),
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'fee_minor' => $order->fee_minor,
            'tax_minor' => $order->buyerTaxMinor(),
            'tax_label' => config('ticketflow.tax.label'),
            'total_minor' => $order->total_minor,
            'promo' => $order->promoCode?->code,
            'buyer' => ['name' => $order->buyer_name, 'email' => $order->buyer_email, 'phone' => $order->buyer_phone ?? ''],
            'payment_methods' => $this->availablePaymentMethods(),
            'held' => true,
        ]);
    }

    /**
     * A Mobile Money attempt failed: return the buyer to checkout to fix the number or choose
     * another method themselves. Only this browser's own order reopens there.
     */
    private function backToCheckout(Order $order, string $message): RedirectResponse
    {
        if (session('checkout_order') !== $order->reference) {
            return redirect()->route('checkout.complete', $order->reference)->with('error', $message);
        }

        return redirect()->route('checkout.show')->withErrors(['phone' => $message]);
    }

    /** The unpaid order this browser last placed, while its tickets are still held. */
    private function heldOrder(Request $request): ?Order
    {
        $reference = $request->session()->get('checkout_order');
        $order = $reference ? Order::where('reference', $reference)->first() : null;

        return $order && $this->canRetryPayment($order) ? $order : null;
    }

    /** The promoter whose ?ref= link brought this buyer, if it applies to the event. */
    private function referringPromoter(Request $request, Event $event): ?Promoter
    {
        $id = $request->session()->get("referrals.{$event->organization_id}");
        $promoter = $id ? Promoter::find($id) : null;

        return $promoter?->covers($event) ? $promoter : null;
    }

    /** Provider return URL: verify server-side and fulfil. */
    public function callback(Request $request, string $reference): RedirectResponse
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $payment = $order->payments()->latest()->firstOrFail();

        try {
            $ok = $this->payments->verifyAndFulfill($payment);
        } catch (RuntimeException $e) {
            return redirect()->route('checkout.complete', $reference)->with('error', $this->customerMessage($e));
        }

        return redirect()->route('checkout.complete', $reference)
            ->with($ok ? 'success' : 'error', $ok ? 'Payment confirmed!' : 'Payment was not completed.');
    }

    public function complete(Request $request, string $reference): Response
    {
        $order = Order::where('reference', $reference)->with(['event', 'tickets.ticketType'])->firstOrFail();

        return Inertia::render('Checkout/Complete', [
            'order' => [
                'reference' => $order->reference,
                'status' => $order->status,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'buyer_email' => $order->buyer_email,
                'can_retry' => $this->canRetryPayment($order),
                'payment_method' => $order->payments()->latest('id')->value('method'),
                'reserved_until' => $order->reserved_until?->toIso8601String(),
                'event' => ['title' => $order->event->title, 'slug' => $order->event->slug],
                'tickets' => $order->tickets->map(fn ($t) => [
                    'code' => $t->code, 'type' => $t->ticketType->name,
                    'attendee_name' => $t->attendee_name,
                    'download_url' => URL::signedRoute('tickets.download', $t->code),
                ]),
            ],
        ]);
    }
}
