<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Domain\Subscriptions\SubscriptionService;
use App\Models\LedgerTransaction;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.payments.default' => 'fake', 'ticketflow.tax.bps' => 1800]);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    private function organizerOn(string $planSlug): array
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $org = Organization::create(['owner_id' => $user->id, 'name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner', 'accepted_at' => now()]);
        app(SubscriptionService::class)->subscribeToFree($org, SubscriptionPlan::where('slug', $planSlug)->firstOrFail());

        return [$user, $org];
    }

    private function eventFor(Organization $org, int $priceMinor = 10000000, string $status = 'published'): array
    {
        $event = $org->events()->create([
            'title' => 'Summit', 'slug' => 'summit-'.uniqid(), 'status' => $status, 'published_at' => $status === 'published' ? now() : null,
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => $priceMinor, 'currency' => 'UGX',
            'quantity_total' => 100, 'max_per_order' => 10, 'status' => 'active',
        ]);

        return [$event, $type];
    }

    private function pay($order): LedgerTransaction
    {
        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'fake', 'provider_ref' => 'FAKE-'.uniqid(), 'idempotency_key' => uniqid(),
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        $this->assertTrue(app(PaymentService::class)->verifyAndFulfill($payment));

        return LedgerTransaction::where('type', 'ticket_sale')->latest('id')->firstOrFail();
    }

    private function credited(LedgerTransaction $txn, string $code): int
    {
        return (int) $txn->entries()->where('direction', 'credit')->whereHas('account', fn ($q) => $q->where('code', $code))->sum('amount_minor');
    }

    public function test_professional_takes_four_and_a_half_percent_plus_tax_from_the_organizer(): void
    {
        [, $org] = $this->organizerOn('professional');
        [$event, $type] = $this->eventFor($org); // 100,000.00 per ticket

        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 2]]);

        // 200,000.00 sale: 4.5% commission 9,000.00 and 18% tax on it 1,620.00, both off the organizer.
        $this->assertSame(20000000, $order->total_minor);
        $this->assertSame(900000, $order->commission_minor);
        $this->assertSame(162000, $order->tax_minor);
        $this->assertSame(18938000, $order->organizerNetMinor());

        $txn = $this->pay($order);
        $this->assertTrue($txn->isBalanced());
        $this->assertSame(900000, $this->credited($txn, 'COMMISSION_INCOME'));
        $this->assertSame(162000, $this->credited($txn, 'TAX_PAYABLE'));
        $this->assertSame(18938000, $this->credited($txn, 'ORGANIZER_PAYABLE'));
    }

    public function test_enterprise_fee_is_three_point_seven_percent_and_buyer_can_pay_it_with_tax(): void
    {
        [, $org] = $this->organizerOn('starter');
        $org->subscription()->update(['status' => 'canceled']);
        $enterprise = SubscriptionPlan::where('slug', 'enterprise')->firstOrFail();
        $org->subscription()->create([
            'subscription_plan_id' => $enterprise->id, 'status' => 'active', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
            'price_minor' => $enterprise->price_minor, 'currency' => 'UGX', 'interval' => 'monthly',
        ]);
        [$event, $type] = $this->eventFor($org);
        $event->update(['fee_payer' => 'buyer']);

        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);

        // 100,000.00 ticket: 3.7% booking fee 3,700.00 plus 18% tax 666.00 added for the buyer.
        $this->assertSame(370000, $order->fee_minor);
        $this->assertSame(66600, $order->tax_minor);
        $this->assertSame(10436600, $order->total_minor);
        $this->assertSame(10000000, $order->organizerNetMinor());

        $txn = $this->pay($order);
        $this->assertTrue($txn->isBalanced());
        $this->assertSame(66600, $this->credited($txn, 'TAX_PAYABLE'));
        $this->assertSame(10000000, $this->credited($txn, 'ORGANIZER_PAYABLE'));
    }

    public function test_starter_allows_one_event_ever_and_professional_is_a_free_switch(): void
    {
        [$user, $org] = $this->organizerOn('starter');
        [$first] = $this->eventFor($org, status: 'published');
        $first->update(['status' => 'unpublished']); // taking it down does not give the free event back
        [$second] = $this->eventFor($org, status: 'draft');

        $this->actingAs($user)->post("/dashboard/events/{$second->slug}/publish")->assertSessionHas('error');
        $this->assertSame('draft', $second->fresh()->status);

        $professional = SubscriptionPlan::where('slug', 'professional')->firstOrFail();
        $this->actingAs($user)->post('/dashboard/subscription', ['plan_id' => $professional->id])->assertSessionHas('success');
        $this->assertSame('professional', $org->fresh()->currentPlan()->slug);

        $this->actingAs($user)->post("/dashboard/events/{$second->slug}/publish")->assertSessionHas('success');
        $this->assertSame('in_review', $second->fresh()->status);
    }
}
