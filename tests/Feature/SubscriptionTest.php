<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Subscriptions\SubscriptionService;
use App\Enums\OrganizationRole;
use App\Models\LedgerTransaction;
use App\Models\Organization;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://gate.paytota.test/api/v1/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'inertia.ssr.enabled' => false,
            'ticketflow.demo_mode' => false,
            'ticketflow.payments.default' => 'paytota',
            'ticketflow.payments.paytota' => ['brand_id' => 'brand-123', 'api_key' => 'test-api-key', 'public_key' => null, 'base_url' => self::BASE],
        ]);
    }

    /** @return array{0: User, 1: Organization} */
    private function organizerWithOrg(): array
    {
        $this->seed([ChartOfAccountsSeeder::class, SubscriptionPlanSeeder::class]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);

        return [$user, Organization::firstOrFail()];
    }

    private function plan(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', $slug)->firstOrFail();
    }

    /** Choose a paid plan the way the dashboard does: an Inertia visit. */
    private function choosePlan(User $user, SubscriptionPlan $plan): TestResponse
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['id' => 'pur_plan', 'checkout_url' => 'https://gate.paytota.test/p/pur_plan/'])]);

        return $this->actingAs($user)->post('/dashboard/subscription', ['plan_id' => $plan->id], ['X-Inertia' => 'true']);
    }

    /** Return from Paytota with the purchase in the given state. */
    private function returnFromCheckout(User $user, string $status, int $totalMajor = 500000): TestResponse
    {
        Http::fake([self::BASE.'purchases/pur_plan/' => Http::response(['status' => $status, 'purchase' => ['total' => $totalMajor, 'currency' => 'UGX']])]);

        return $this->actingAs($user)->get('/dashboard/subscription/invoices/'.SubscriptionInvoice::sole()->number.'/callback');
    }

    public function test_new_organization_is_on_starter_with_its_commission_and_no_paid_features(): void
    {
        [, $org] = $this->organizerWithOrg();

        $this->assertSame('starter', $org->currentPlan()->slug);
        $this->assertSame(1000, $org->commissionBps());
        $this->assertFalse($org->planAllows('promo_codes'));
    }

    public function test_choosing_paid_plan_sends_organizer_to_paytota_without_unlocking_anything(): void
    {
        [$user, $org] = $this->organizerWithOrg();

        $this->choosePlan($user, $this->plan('enterprise'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://gate.paytota.test/p/pur_plan/');

        $this->assertSame('starter', $org->currentPlan()->slug);
        $this->assertSame('open', SubscriptionInvoice::sole()->status);
        $this->assertSame(50000000, SubscriptionInvoice::sole()->amount_minor);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/' && $request['purchase']['products'][0]['price'] == 500000);
    }

    public function test_switching_to_professional_is_free_and_unlocks_its_features_at_once(): void
    {
        [$user, $org] = $this->organizerWithOrg();

        $this->actingAs($user)->post('/dashboard/subscription', ['plan_id' => $this->plan('professional')->id])
            ->assertSessionHas('success', "You're on the Professional plan.");

        $this->assertSame('professional', $org->currentPlan()->slug);
        $this->assertSame(1000, $org->commissionBps());
        $this->assertTrue($org->planAllows('promo_codes'));
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_verified_payment_activates_plan_lowers_commission_and_records_revenue(): void
    {
        [$user, $org] = $this->organizerWithOrg();
        $this->choosePlan($user, $this->plan('enterprise'));

        $this->returnFromCheckout($user, 'paid')
            ->assertRedirect('/dashboard/subscription')
            ->assertSessionHas('success', 'Payment received. Your Enterprise plan is now active.');

        $this->assertSame('enterprise', $org->currentPlan()->slug);
        $this->assertSame(370, $org->commissionBps());
        $this->assertTrue($org->planAllows('promo_codes'));
        $this->assertSame('paid', SubscriptionInvoice::sole()->status);
        $this->assertTrue(LedgerTransaction::where('type', 'subscription')->exists());
    }

    public function test_unpaid_return_from_checkout_keeps_current_plan(): void
    {
        [$user, $org] = $this->organizerWithOrg();
        $this->choosePlan($user, $this->plan('enterprise'));

        $this->returnFromCheckout($user, 'cancelled')
            ->assertSessionHas('error', 'The payment was not completed, so your plan has not changed. You can try again below.');

        $this->assertSame('starter', $org->currentPlan()->slug);
        $this->assertSame('open', SubscriptionInvoice::sole()->status);
    }

    public function test_payment_for_less_than_the_invoice_does_not_activate_plan(): void
    {
        [$user, $org] = $this->organizerWithOrg();
        $this->choosePlan($user, $this->plan('enterprise'));

        $this->returnFromCheckout($user, 'paid', totalMajor: 1000)->assertSessionHas('error');

        $this->assertSame('starter', $org->currentPlan()->slug);
    }

    public function test_member_without_finance_role_cannot_buy_plan(): void
    {
        [, $org] = $this->organizerWithOrg();
        $staff = User::factory()->create();
        $org->members()->create(['user_id' => $staff->id, 'role' => OrganizationRole::Staff, 'accepted_at' => now()]);

        $this->actingAs($staff)->post('/dashboard/subscription', ['plan_id' => $this->plan('enterprise')->id])->assertForbidden();
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_another_organizations_invoice_callback_is_not_found(): void
    {
        [$user] = $this->organizerWithOrg();
        $this->choosePlan($user, $this->plan('enterprise'));
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post('/organizations', ['name' => 'Other Org']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);

        $this->actingAs($outsider)->get('/dashboard/subscription/invoices/'.SubscriptionInvoice::sole()->number.'/callback')->assertNotFound();
    }

    public function test_locked_feature_redirects_to_plans_naming_the_plan_that_unlocks_it(): void
    {
        [$user] = $this->organizerWithOrg();

        $this->actingAs($user)->get('/dashboard/reports')
            ->assertRedirect('/dashboard/subscription')
            ->assertSessionHas('error', 'Financial reports and exports is part of the Enterprise plan. Upgrade to unlock it.');
    }

    public function test_promo_code_stops_working_when_plan_lacks_promo_codes(): void
    {
        [, $org] = $this->organizerWithOrg();
        $event = $org->events()->create(['title' => 'Summit', 'slug' => 'summit', 'status' => 'published', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX']);
        $type = $event->ticketTypes()->create(['name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX', 'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active']);
        $org->promoCodes()->create(['code' => 'EARLY', 'type' => 'percent', 'value' => 2000]);

        $this->expectExceptionObject(new RuntimeException('This promo code is invalid or has expired.'));
        app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]], promoCode: 'EARLY');
    }

    public function test_order_beyond_plan_ticket_limit_is_refused_and_reserves_nothing(): void
    {
        [, $org] = $this->organizerWithOrg();
        $event = $org->events()->create(['title' => 'Summit', 'slug' => 'summit', 'status' => 'published', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX']);
        $type = $event->ticketTypes()->create(['name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX', 'quantity_total' => 500, 'quantity_sold' => 199, 'max_per_order' => 10, 'status' => 'active']);

        try {
            app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 2]]);
            $this->fail('The order should have been refused.');
        } catch (RuntimeException $e) {
            $this->assertSame('This event has sold all the tickets its organizer can offer right now.', $e->getMessage());
        }

        $this->assertSame(0, $type->fresh()->quantity_reserved);
    }

    public function test_expired_paid_plan_falls_back_to_starter(): void
    {
        [, $org] = $this->organizerWithOrg();
        $this->onPaidPlan($org);

        $this->travel(32)->days();

        $this->assertSame('starter', $org->currentPlan()->slug);
        $this->assertFalse($org->planAllows('promo_codes'));
    }

    public function test_renewing_running_plan_extends_from_end_of_current_period(): void
    {
        [, $org] = $this->organizerWithOrg();
        $pro = $this->plan('enterprise');
        $service = app(SubscriptionService::class);
        $service->activate($service->checkout($org, $pro));
        $firstEnd = $org->activeSubscription()->current_period_end;

        $renewed = $service->activate($service->checkout($org, $pro));

        $this->assertSame($firstEnd->copy()->addMonth()->toDateTimeString(), $renewed->current_period_end->toDateTimeString());
    }

    public function test_switching_to_free_plan_keeps_paid_plan_until_period_end(): void
    {
        [$user, $org] = $this->organizerWithOrg();
        $this->onPaidPlan($org);

        $this->actingAs($user)->post('/dashboard/subscription', ['plan_id' => $this->plan('starter')->id])->assertSessionHas('success');

        $this->assertTrue($org->activeSubscription()->cancel_at_period_end);
        $this->assertTrue($org->planAllows('promo_codes'));
    }

    public function test_cancel_and_resume(): void
    {
        [$user, $org] = $this->organizerWithOrg();
        $this->onPaidPlan($org);

        $this->actingAs($user)->post('/dashboard/subscription/cancel')->assertRedirect();
        $this->assertTrue($org->activeSubscription()->cancel_at_period_end);

        $this->actingAs($user)->post('/dashboard/subscription/resume')->assertRedirect();
        $this->assertFalse($org->activeSubscription()->cancel_at_period_end);
    }

    public function test_publish_is_blocked_when_no_plan_exists_at_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);

        $this->assertFalse(app(SubscriptionService::class)->canPublishEvents(Organization::firstOrFail()));
    }

    public function test_subscription_page_renders(): void
    {
        [$user] = $this->organizerWithOrg();

        $this->actingAs($user)->get('/dashboard/subscription')->assertOk();
    }
}
