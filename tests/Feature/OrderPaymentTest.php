<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.payments.default' => 'fake', 'ticketflow.commission_bps' => 500]);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function publishedEvent(int $qty = 100, int $priceMinor = 5000000): array
    {
        $user = User::factory()->create();
        $org = Organization::create(['owner_id' => $user->id, 'name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create([
            'title' => 'Summit', 'slug' => 'summit-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => $priceMinor, 'currency' => 'UGX',
            'quantity_total' => $qty, 'max_per_order' => 50, 'status' => 'active',
        ]);

        return [$event, $type];
    }

    private function buyer(): array
    {
        return ['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '+256700000000'];
    }

    public function test_order_reserves_inventory(): void
    {
        [$event, $type] = $this->publishedEvent(qty: 10);

        app(OrderService::class)->create($event, $this->buyer(), [
            ['ticket_type_id' => $type->id, 'quantity' => 3],
        ]);

        $this->assertSame(3, $type->fresh()->quantity_reserved);
        $this->assertSame(7, $type->fresh()->availableQuantity());
        $this->assertSame(0, $type->fresh()->quantity_sold);
    }

    public function test_overselling_is_prevented(): void
    {
        [$event, $type] = $this->publishedEvent(qty: 2);
        $svc = app(OrderService::class);

        $svc->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 2]]);

        $this->expectException(\RuntimeException::class);
        $svc->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 1]]);
    }

    public function test_tickets_not_issued_before_verified_payment(): void
    {
        [$event, $type] = $this->publishedEvent();
        $order = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 2]]);

        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(0, $order->tickets()->count());
        $this->assertSame(0, $type->fresh()->quantity_sold);
    }

    public function test_verified_payment_issues_tickets_and_posts_balanced_ledger(): void
    {
        [$event, $type] = $this->publishedEvent(priceMinor: 10000000); // 100,000.00
        $order = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 2]]);

        // subtotal = 2 * 100000.00 = 200,000.00 ; commission 5% = 10,000.00
        $this->assertSame(20000000, $order->subtotal_minor);
        $this->assertSame(1000000, $order->commission_minor);

        $payment = Payment::create([
            'reference' => 'PAY-TEST', 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'fake', 'provider_ref' => 'FAKE-1', 'idempotency_key' => 'k1',
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);

        $ok = app(PaymentService::class)->verifyAndFulfill($payment);

        $this->assertTrue($ok);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, $order->fresh()->tickets()->count());
        $this->assertSame(2, $type->fresh()->quantity_sold);
        $this->assertSame(0, $type->fresh()->quantity_reserved);

        // Each ticket has a unique non-empty QR token.
        $tokens = $order->fresh()->tickets->pluck('qr_token');
        $this->assertCount(2, $tokens->unique());
        $this->assertNotEmpty($tokens->first());

        // Ledger: one balanced ticket_sale transaction; commission income = 10,000.00
        $txn = LedgerTransaction::where('type', 'ticket_sale')->firstOrFail();
        $this->assertTrue($txn->isBalanced());
        $this->assertSame(1000000, (int) $txn->entries()->where('direction', 'credit')
            ->whereHas('account', fn ($q) => $q->where('code', 'COMMISSION_INCOME'))->sum('amount_minor'));
    }

    public function test_fulfillment_is_idempotent(): void
    {
        [$event, $type] = $this->publishedEvent();
        $order = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 2]]);
        $payment = Payment::create([
            'reference' => 'PAY-TEST2', 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'fake', 'provider_ref' => 'FAKE-2', 'idempotency_key' => 'k2',
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        $svc = app(PaymentService::class);

        $svc->verifyAndFulfill($payment);
        $svc->verifyAndFulfill($payment->fresh()); // retry / duplicate webhook

        $this->assertSame(2, $order->fresh()->tickets()->count());          // not doubled
        $this->assertSame(1, LedgerTransaction::where('type', 'ticket_sale')->count()); // posted once
        $this->assertSame(2, $type->fresh()->quantity_sold);
    }

    public function test_free_order_fulfils_without_payment(): void
    {
        [$event, $type] = $this->publishedEvent(priceMinor: 0);
        $type->update(['kind' => 'free']);
        $order = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 1]]);

        app(PaymentService::class)->fulfillFree($order);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, $order->fresh()->tickets()->count());
    }

    public function test_demo_checkout_completes_without_external_provider_redirect(): void
    {
        [$event, $type] = $this->publishedEvent(priceMinor: 5000000);
        config(['ticketflow.demo_mode' => true]);

        $response = $this->withSession([
            'checkout' => [
                'event_id' => $event->id,
                'items' => [['ticket_type_id' => $type->id, 'quantity' => 1]],
            ],
        ])->post('/checkout', $this->buyer());

        $order = Order::firstOrFail();
        $response->assertRedirect(route('checkout.complete', $order->reference));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('successful', $order->payments()->first()->status);
        $this->assertCount(1, $order->fresh()->tickets);
    }

    public function test_expired_reservations_are_returned_to_stock_once(): void
    {
        [$event, $type] = $this->publishedEvent(qty: 10);
        $expired = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 3]]);
        $active = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 2]]);
        $expired->update(['reserved_until' => now()->subMinute()]);

        $this->artisan('orders:release-expired')->assertSuccessful();
        $this->artisan('orders:release-expired')->assertSuccessful();

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('awaiting_payment', $active->fresh()->status);
        $this->assertSame(2, $type->fresh()->quantity_reserved);
        $this->assertSame(8, $type->fresh()->availableQuantity());
    }

    public function test_late_payment_after_hold_expired_does_not_release_stock_twice(): void
    {
        [$event, $type] = $this->publishedEvent(qty: 10);
        $late = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 2]]);
        $other = app(OrderService::class)->create($event, $this->buyer(), [['ticket_type_id' => $type->id, 'quantity' => 3]]);
        $late->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('orders:release-expired');

        $payment = Payment::create([
            'reference' => 'PAY-LATE', 'purpose' => 'ticket_order', 'order_id' => $late->id,
            'provider' => 'fake', 'provider_ref' => 'FAKE-LATE', 'idempotency_key' => 'late',
            'amount_minor' => $late->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        app(PaymentService::class)->verifyAndFulfill($payment);

        $type->refresh();
        $this->assertSame(2, $late->fresh()->tickets()->count());
        $this->assertSame(2, $type->quantity_sold);
        // The other buyer's hold is untouched.
        $this->assertSame(3, $type->quantity_reserved);
        $this->assertSame('awaiting_payment', $other->fresh()->status);
    }
}
