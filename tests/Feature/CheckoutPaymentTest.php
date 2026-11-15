<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://gate.paytota.test/api/v1/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'inertia.ssr.enabled' => false,
            'ticketflow.demo_mode' => false,
            'ticketflow.payments.default' => 'paytota',
            'ticketflow.payments.paytota' => [
                'brand_id' => 'brand-123', 'api_key' => 'test-api-key', 'public_key' => null, 'base_url' => self::BASE,
                'mobile_money' => true,
            ],
        ]);
    }

    private function eventWithTickets(): array
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create([
            'title' => 'Summit', 'slug' => 'summit', 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active',
        ]);

        return [$event, $type];
    }

    /** Submit the checkout form the way the React page does: as an Inertia visit. */
    private function checkout(Event $event, int $ticketTypeId, array $overrides = [])
    {
        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $ticketTypeId, 'quantity' => 2]]]);

        return $this->post('/checkout', [
            'name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '+256700000000', ...$overrides,
        ], ['X-Inertia' => 'true']);
    }

    private function fakeMobileMoneyPurchase(string $status = 'pending_execute'): void
    {
        Http::fake([
            self::BASE.'purchases/' => Http::response(['id' => 'pur_mm', 'checkout_url' => 'https://pay.paytota.test/p/pur_mm/invoice/']),
            'https://gate.paytota.test/p/pur_mm/*' => Http::response(['status' => 'pending', 'details' => ['return_code' => '200', 'message' => 'Transaction received for processing']]),
            self::BASE.'purchases/pur_mm/' => Http::response(['status' => $status, 'purchase' => ['total' => 100000, 'currency' => 'UGX'], 'transaction_data' => ['payment_method' => 'paytota_proxy']]),
        ]);
    }

    public function test_card_payment_restricts_paytota_checkout_to_cards(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['id' => 'pur_1', 'checkout_url' => 'https://gate.paytota.test/p/pur_1/'])]);
        [$event, $type] = $this->eventWithTickets();

        $this->checkout($event, $type->id, ['payment_method' => 'card'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://gate.paytota.test/p/pur_1/');

        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/' && $request['payment_method_whitelist'] === ['visa', 'mastercard', 'maestro', 'american_express', 'discover']);
        $this->assertSame('card', Order::sole()->payments()->sole()->method);
    }

    public function test_mobile_money_sends_a_pin_prompt_and_shows_the_approval_page(): void
    {
        Http::preventStrayRequests();
        $this->fakeMobileMoneyPurchase();
        [$event, $type] = $this->eventWithTickets();

        $response = $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);

        $response->assertRedirect(route('checkout.approve', Order::sole()->reference));
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/' && $request['payment_method_whitelist'] === ['paytota_proxy']);
        Http::assertSent(fn ($request) => $request->url() === 'https://gate.paytota.test/p/pur_mm/?s2s=true'
            && $request['s2s'] === 'true' && $request['pm'] === 'paytota_proxy');
    }

    public function test_mobile_money_requires_a_phone_number(): void
    {
        Http::preventStrayRequests();
        [$event, $type] = $this->eventWithTickets();

        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money', 'phone' => ''])
            ->assertSessionHasErrors(['phone' => 'Enter the Mobile Money number to send the payment prompt to.']);

        $this->assertSame(0, Order::count());
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), self::BASE.'purchases/'));
    }

    public function test_approval_page_waits_while_the_buyer_has_not_entered_their_pin(): void
    {
        Http::preventStrayRequests();
        $this->fakeMobileMoneyPurchase('pending_execute');
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        $order = Order::sole();

        $this->get(route('checkout.approve', $order->reference))
            ->assertInertia(fn ($page) => $page->component('Checkout/AwaitingApproval', false)
                ->where('order.phone_hint', '••••••••0000'));

        $this->assertSame('pending', $order->payments()->sole()->status);
    }

    public function test_approval_page_keeps_waiting_when_paytota_is_unreachable(): void
    {
        Http::preventStrayRequests();
        $this->fakeMobileMoneyPurchase();
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        $order = Order::sole();
        Http::fake([self::BASE.'purchases/pur_mm/' => Http::failedConnection()]);

        $this->get(route('checkout.approve', $order->reference))
            ->assertInertia(fn ($page) => $page->component('Checkout/AwaitingApproval', false));

        $this->assertSame('pending', $order->payments()->sole()->status);
    }

    public function test_approval_page_issues_tickets_once_mobile_money_is_paid(): void
    {
        Http::preventStrayRequests();
        $this->fakeMobileMoneyPurchase('paid');
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        $order = Order::sole();

        $this->get(route('checkout.approve', $order->reference))
            ->assertRedirect(route('checkout.complete', $order->reference));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, $order->tickets()->count());
        $this->assertSame('mobile_money', $order->payments()->sole()->method);
    }

    public function test_declined_mobile_money_payment_returns_the_buyer_to_checkout(): void
    {
        Http::preventStrayRequests();
        $this->fakeMobileMoneyPurchase('error');
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        $order = Order::sole();

        $this->get(route('checkout.approve', $order->reference))
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors(['phone' => 'The Mobile Money payment was declined, cancelled or timed out. Check the number and try again, or choose another payment method.']);

        // Checkout reopens with the buyer's details so they can fix the number or pick a method themselves.
        $this->get('/checkout')->assertInertia(fn ($page) => $page
            ->where('held', true)
            ->where('buyer.phone', '+256700000000')
            ->where('total_minor', $order->total_minor)
            ->where('payment_methods', ['mobile_money', 'card']));
    }

    public function test_buyer_can_switch_from_mobile_money_to_card(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'purchases/' => Http::sequence()
                ->push(['id' => 'pur_mm', 'checkout_url' => 'https://pay.paytota.test/p/pur_mm/'])
                ->push(['id' => 'pur_card', 'checkout_url' => 'https://pay.paytota.test/p/pur_card/']),
            'https://gate.paytota.test/p/pur_mm/*' => Http::response(['status' => 'pending', 'details' => ['return_code' => '200', 'message' => 'Transaction received for processing']]),
        ]);
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        $order = Order::sole();

        $this->post(route('checkout.retry', $order->reference), ['payment_method' => 'card'], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://pay.paytota.test/p/pur_card/');

        $this->assertSame('card', $order->payments()->sole()->method);
    }

    public function test_paying_sends_the_buyer_to_paytota_checkout(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['id' => 'pur_1', 'checkout_url' => 'https://gate.paytota.test/p/pur_1/'])]);
        [$event, $type] = $this->eventWithTickets();

        $this->checkout($event, $type->id)
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://gate.paytota.test/p/pur_1/');

        $this->assertSame('pur_1', Order::sole()->payments()->sole()->provider_ref);
    }

    public function test_provider_outage_shows_retry_instead_of_an_error_page(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['detail' => 'Service unavailable'], 503)]);
        [$event, $type] = $this->eventWithTickets();

        $response = $this->checkout($event, $type->id);
        $order = Order::sole();

        $response->assertRedirect(route('checkout.complete', $order->reference))
            ->assertSessionHas('error', 'We could not reach the payment provider. Your tickets are still held, so please try again.');
        $this->get(route('checkout.complete', $order->reference))
            ->assertInertia(fn ($page) => $page->component('Checkout/Complete', false)->where('order.can_retry', true));
    }

    public function test_retry_starts_a_new_paytota_purchase_for_a_held_order(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::sequence()
            ->push(['detail' => 'Service unavailable'], 503)
            ->push(['id' => 'pur_2', 'checkout_url' => 'https://gate.paytota.test/p/pur_2/'])]);
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id);
        $order = Order::sole();

        $this->post(route('checkout.retry', $order->reference), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://gate.paytota.test/p/pur_2/');
    }

    public function test_retry_is_refused_once_the_ticket_hold_has_expired(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['detail' => 'Service unavailable'], 503)]);
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id);
        $order = Order::sole();
        $this->travel(config('ticketflow.reservation_minutes') + 1)->minutes();

        $this->post(route('checkout.retry', $order->reference))
            ->assertRedirect(route('checkout.complete', $order->reference))
            ->assertSessionHas('error', 'This order can no longer be paid. Please start a new order.');
        $this->assertCount(1, Http::recorded(fn ($request) => $request->url() === self::BASE.'purchases/'));
    }

    public function test_checkout_offers_card_only_while_mobile_money_is_switched_off(): void
    {
        Http::preventStrayRequests();
        config(['ticketflow.payments.paytota.mobile_money' => false]);
        [$event, $type] = $this->eventWithTickets();
        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $type->id, 'quantity' => 2]]]);

        $this->get('/checkout')->assertInertia(fn ($page) => $page->where('payment_methods', ['card']));
        $this->post('/checkout', ['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '+256700000000', 'payment_method' => 'mobile_money'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_retry_falls_back_to_card_once_mobile_money_is_switched_off(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'purchases/' => Http::sequence()
                ->push(['id' => 'pur_mm', 'checkout_url' => 'https://pay.paytota.test/p/pur_mm/'])
                ->push(['id' => 'pur_card', 'checkout_url' => 'https://pay.paytota.test/p/pur_card/']),
            'https://gate.paytota.test/p/pur_mm/*' => Http::response(['status' => 'pending', 'details' => ['return_code' => '200', 'message' => 'Transaction received for processing']]),
        ]);
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        config(['ticketflow.payments.paytota.mobile_money' => false]);

        $this->post(route('checkout.retry', Order::sole()->reference), [], ['X-Inertia' => 'true'])
            ->assertHeader('X-Inertia-Location', 'https://pay.paytota.test/p/pur_card/');
    }

    public function test_missing_mobile_money_terminal_returns_the_buyer_to_checkout_without_switching_to_card(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'purchases/' => Http::response(['id' => 'pur_mm', 'checkout_url' => 'https://pay.paytota.test/p/pur_mm/invoice/']),
            // Without a Mobile Money terminal Paytota falls back to the card form, cancels the
            // purchase and redirects to failure_redirect, which points back at this app.
            'https://gate.paytota.test/p/pur_mm/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1:8000/checkout/TF-X/callback']),
            self::BASE.'purchases/pur_mm/' => Http::response([
                'status' => 'cancelled',
                'purchase' => ['total' => 100000, 'currency' => 'UGX'],
                'transaction_data' => ['attempts' => [['error' => ['code' => 'no_matching_terminal', 'message' => 'No matching terminal']]]],
            ]),
        ]);
        [$event, $type] = $this->eventWithTickets();

        $response = $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);

        $order = Order::sole();
        $response->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrors(['phone' => 'Mobile Money is not available right now. Please try again later, or choose another payment method.']);
        $this->assertSame('failed', $order->payments()->sole()->status);
        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }

    public function test_paying_again_from_the_checkout_page_retries_the_held_order(): void
    {
        Http::preventStrayRequests();
        $this->fakeMobileMoneyPurchase();
        [$event, $type] = $this->eventWithTickets();
        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money']);
        $order = Order::sole();

        // The buyer went back to the checkout page, entered another number and clicked Pay again.
        $response = $this->post('/checkout', [
            'name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0772123456', 'payment_method' => 'mobile_money',
        ], ['X-Inertia' => 'true']);

        $response->assertRedirect(route('checkout.approve', $order->reference));
        $this->assertSame(1, Order::count(), 'no second order, so no second ticket hold');
        $this->assertSame('0772123456', $order->fresh()->buyer_phone);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/' && $request['client']['phone'] === '256772123456');
        $this->get('/checkout')->assertInertia(fn ($page) => $page->where('held', true)->where('buyer.phone', '0772123456'));
    }

    public function test_paying_with_no_cart_or_held_order_explains_the_checkout_expired(): void
    {
        $this->post('/checkout', ['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0772123456'])
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error', 'Your checkout has expired. Please choose your tickets again.');
    }

    public function test_mobile_money_prompt_accepted_by_a_redirect_to_the_pending_page_shows_the_approval_page(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            // Live Paytota: checkout_url is on another host, and an accepted prompt redirects to /pending/.
            self::BASE.'purchases/' => Http::response(['id' => 'pur_mm', 'checkout_url' => 'https://payments.paytota.test/p/pur_mm/']),
            'https://gate.paytota.test/p/pur_mm/*' => Http::response('', 303, ['Location' => 'https://gate.paytota.test/p/pur_mm/pending/']),
        ]);
        [$event, $type] = $this->eventWithTickets();

        $this->checkout($event, $type->id, ['payment_method' => 'mobile_money', 'phone' => '0761664422'])
            ->assertRedirect(route('checkout.approve', Order::sole()->reference));
        Http::assertSent(fn ($request) => $request->url() === 'https://gate.paytota.test/p/pur_mm/?s2s=true' && $request['pm'] === 'paytota_proxy');
    }

    public function test_checkout_adds_the_booking_fee_when_buyers_pay_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['id' => 'pur_1', 'checkout_url' => 'https://gate.paytota.test/p/pur_1/'])]);
        [$event, $type] = $this->eventWithTickets();
        $event->update(['fee_payer' => 'buyer']);
        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $type->id, 'quantity' => 2]]]);

        // 2 x 50,000 = 100,000 plus the 4.5% booking fee.
        $this->get('/checkout')->assertInertia(fn ($page) => $page
            ->where('subtotal_minor', 10000000)
            ->where('fee_minor', 450000)
            ->where('total_minor', 10450000));

        $this->post('/checkout', ['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '0761664422', 'payment_method' => 'card'], ['X-Inertia' => 'true'])
            ->assertStatus(409);
        $this->assertSame(10450000, Order::sole()->total_minor);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/' && $request['purchase']['products'][0]['price'] === 104500);
    }
}
