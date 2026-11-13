<?php

namespace Tests\Feature;

use App\Domain\Contributions\ContributionService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Domain\Payouts\PayoutService;
use App\Models\Event;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionPayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.payments.default' => 'fake', 'ticketflow.commission_bps' => 500, 'ticketflow.contribution_commission_bps' => 300, 'ticketflow.payout_settlement_days' => 7]);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->owner = User::factory()->create();
        $this->org = Organization::create(['owner_id' => $this->owner->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $this->org->members()->create(['user_id' => $this->owner->id, 'role' => 'owner', 'accepted_at' => now()]);
    }

    private function settledPaidOrder(int $priceMinor = 10000000): Order
    {
        $event = $this->org->events()->create([
            'title' => 'Past Event', 'slug' => 'past-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->subDays(10), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create(['name' => 'Reg', 'kind' => 'paid', 'price_minor' => $priceMinor, 'currency' => 'UGX', 'quantity_total' => 100, 'max_per_order' => 10, 'status' => 'active']);
        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@e.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        $payment = Payment::create(['reference' => 'P'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id, 'provider' => 'fake', 'provider_ref' => 'R'.uniqid(), 'idempotency_key' => uniqid(), 'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending']);
        app(PaymentService::class)->verifyAndFulfill($payment);

        return $order->fresh();
    }

    public function test_contribution_fulfils_and_posts_balanced_ledger(): void
    {
        $campaign = $this->org->campaigns()->create(['title' => 'Build a well', 'slug' => 'well', 'currency' => 'UGX', 'status' => 'active', 'raised_minor' => 0]);
        $c = app(ContributionService::class)->create($campaign, ['name' => 'Jane', 'email' => 'j@e.com'], 5000000);
        $this->assertSame(150000, $c->commission_minor); // 3%

        $payment = Payment::create(['reference' => 'PC'.uniqid(), 'purpose' => 'contribution', 'contribution_id' => $c->id, 'provider' => 'fake', 'provider_ref' => 'RC'.uniqid(), 'idempotency_key' => uniqid(), 'amount_minor' => $c->amount_minor, 'currency' => 'UGX', 'status' => 'pending']);
        app(PaymentService::class)->verifyAndFulfill($payment);

        $c->refresh();
        $this->assertSame('successful', $c->status);
        $this->assertNotNull($c->receipt_number);
        $this->assertSame(5000000, $campaign->fresh()->raised_minor);
        $this->assertSame(1, $campaign->fresh()->contributors_count);

        $txn = LedgerTransaction::where('type', 'contribution')->firstOrFail();
        $this->assertTrue($txn->isBalanced());
    }

    public function test_funds_not_eligible_before_settlement(): void
    {
        // Event in the future → not settled.
        $event = $this->org->events()->create(['title' => 'Future', 'slug' => 'fut', 'status' => 'published', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addDays(10), 'currency' => 'UGX']);
        $type = $event->ticketTypes()->create(['name' => 'R', 'kind' => 'paid', 'price_minor' => 10000000, 'currency' => 'UGX', 'quantity_total' => 50, 'max_per_order' => 5, 'status' => 'active']);
        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@e.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        $payment = Payment::create(['reference' => 'P'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id, 'provider' => 'fake', 'provider_ref' => 'R'.uniqid(), 'idempotency_key' => uniqid(), 'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending']);
        app(PaymentService::class)->verifyAndFulfill($payment);

        $this->assertSame(0, app(PayoutService::class)->availableMinor($this->org));
    }

    public function test_demo_payout_settles_a_future_event_immediately(): void
    {
        config(['ticketflow.demo_mode' => true]);

        $event = $this->org->events()->create([
            'title' => 'Demo Future Event', 'slug' => 'demo-future-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addDays(30), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Demo pass', 'kind' => 'paid', 'price_minor' => 10000000, 'currency' => 'UGX',
            'quantity_total' => 50, 'max_per_order' => 5, 'status' => 'active',
        ]);
        $order = app(OrderService::class)->create($event, ['name' => 'Demo buyer', 'email' => 'demo@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        $payment = Payment::create([
            'reference' => 'PD'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'fake', 'provider_ref' => 'RD'.uniqid(), 'idempotency_key' => uniqid(),
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        app(PaymentService::class)->verifyAndFulfill($payment);

        $this->assertSame(9500000, app(PayoutService::class)->availableMinor($this->org));
        $this->org->payoutAccounts()->create(['type' => 'mobile_money', 'provider' => 'DEMO', 'account_name' => 'Demo', 'account_number' => '0000', 'currency' => 'UGX', 'is_default' => true]);

        $payout = app(PayoutService::class)->request($this->org);

        $this->assertSame('paid', $payout->status);
        $this->assertNotNull($payout->processed_at);
    }

    public function test_payout_request_and_admin_approval_settles_ledger(): void
    {
        $order = $this->settledPaidOrder(); // net = 9,500,000
        $svc = app(PayoutService::class);

        $this->assertSame(9500000, $svc->availableMinor($this->org));

        $this->org->payoutAccounts()->create(['type' => 'mobile_money', 'provider' => 'MTN', 'account_name' => 'Acme', 'account_number' => '0700', 'currency' => 'UGX', 'is_default' => true]);
        $payout = $svc->request($this->org);

        $this->assertSame('requested', $payout->status);
        $this->assertSame(9500000, $payout->amount_minor);
        $this->assertSame(0, $svc->availableMinor($this->org->fresh())); // nothing left eligible

        $admin = User::factory()->admin()->create();
        $svc->markPaid($payout, $admin->id);

        $this->assertSame('paid', $payout->fresh()->status);
        // Organizer payable nets to zero after payout.
        $payable = app(LedgerService::class)->organizerPayable($this->org, 'UGX');
        $this->assertSame(0, $payable->balanceMinor());
    }

    public function test_admin_area_requires_admin(): void
    {
        $this->actingAs($this->owner)->get('/admin')->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
