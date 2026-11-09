<?php

namespace App\Http\Controllers;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
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

        return redirect()->route('checkout.show');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $checkout = $request->session()->get('checkout');
        if (! $checkout) {
            return redirect()->route('events.index');
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

        $user = $request->user();

        return Inertia::render('Checkout/Index', [
            'event' => ['title' => $event->title, 'slug' => $event->slug, 'currency' => $event->currency],
            'lines' => $lines,
            'subtotal_minor' => $subtotal,
            'buyer' => ['name' => $user?->name ?? '', 'email' => $user?->email ?? '', 'phone' => ''],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $checkout = $request->session()->get('checkout');
        if (! $checkout) {
            return redirect()->route('events.index');
        }

        $buyer = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $event = Event::findOrFail($checkout['event_id']);

        try {
            $order = $this->orders->create($event, $buyer, $checkout['items'], $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->forget('checkout');

        // Free order: issue immediately, no payment.
        if ($order->total_minor <= 0) {
            $this->payments->fulfillFree($order);

            return redirect()->route('checkout.complete', $order->reference);
        }

        $intent = $this->payments->initiateForOrder(
            $order,
            route('checkout.callback', $order->reference),
        );

        // Demo mode should behave like a completed hosted checkout without
        // requiring the browser to follow a fake provider redirect.
        if (config('ticketflow.demo_mode') && config('ticketflow.payments.default') === 'fake') {
            try {
                $this->payments->verifyAndFulfill($order->payments()->latest()->firstOrFail());
            } catch (\RuntimeException $e) {
                return redirect()->route('checkout.complete', $order->reference)->with('error', $e->getMessage());
            }

            return redirect()->route('checkout.complete', $order->reference)->with('success', 'Demo payment confirmed!');
        }

        // Full-page redirect to the provider's hosted checkout (or callback for the dev provider).
        return Inertia::location($intent->redirectUrl);
    }

    /** Provider return URL: verify server-side and fulfil. */
    public function callback(Request $request, string $reference): RedirectResponse
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $payment = $order->payments()->latest()->firstOrFail();

        try {
            $ok = $this->payments->verifyAndFulfill($payment);
        } catch (\RuntimeException $e) {
            return redirect()->route('checkout.complete', $reference)->with('error', $e->getMessage());
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
