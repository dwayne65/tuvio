<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SubscriptionMobileMoneyTest extends TestCase
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
                'dialing_code' => '256', 'mobile_money' => true,
            ],
        ]);
    }

    /** @return array{0: User, 1: Organization} */
    private function organizer(): array
    {
        $this->seed([ChartOfAccountsSeeder::class, SubscriptionPlanSeeder::class]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);

        return [$user, Organization::firstOrFail()];
    }

    /** Paytota accepts the purchase, then answers the PIN prompt request with the given body. */
    private function fakePaytota(array $promptResponse = ['status' => 'pending'], string $purchaseStatus = 'pending_execute'): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'purchases/' => Http::response(['id' => 'pur_mm', 'checkout_url' => 'https://gate.paytota.test/p/pur_mm/']),
            'https://gate.paytota.test/p/pur_mm/*' => Http::response($promptResponse),
            self::BASE.'purchases/pur_mm/' => Http::response(['status' => $purchaseStatus, 'purchase' => ['total' => 500000, 'currency' => 'UGX'], 'transaction_data' => ['payment_method' => 'paytota_proxy']]),
        ]);
    }

    private function payByMobileMoney(User $user, array $overrides = []): TestResponse
    {
        $plan = SubscriptionPlan::where('slug', 'enterprise')->firstOrFail();

        return $this->actingAs($user)->post('/dashboard/subscription', [
            'plan_id' => $plan->id, 'payment_method' => 'mobile_money', 'phone' => '0770 123456', ...$overrides,
        ]);
    }

    public function test_mobile_money_sends_pin_prompt_to_entered_number_and_waits_for_approval(): void
    {
        $this->fakePaytota();
        [$user, $org] = $this->organizer();

        $this->payByMobileMoney($user)->assertRedirect(route('subscription.approve', SubscriptionInvoice::firstOrFail()->number));

        $invoice = SubscriptionInvoice::sole();
        $this->assertSame('0770 123456', $invoice->payer_phone);
        $this->assertSame('starter', $org->currentPlan()->slug);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/'
            && $request['client']['phone'] === '256770123456'
            && $request['payment_method_whitelist'] === ['paytota_proxy']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://gate.paytota.test/p/pur_mm/') && $request['pm'] === 'paytota_proxy');
    }

    public function test_approval_page_waits_while_payment_is_pending(): void
    {
        $this->fakePaytota();
        [$user, $org] = $this->organizer();
        $this->payByMobileMoney($user);

        $this->actingAs($user)->get(route('subscription.approve', SubscriptionInvoice::sole()->number))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard/SubscriptionApproval', false)
                ->where('invoice.phone_hint', '••••••3456')
                ->where('invoice.plan_name', 'Enterprise'));

        $this->assertSame('starter', $org->currentPlan()->slug);
    }

    public function test_approved_pin_activates_plan(): void
    {
        $this->fakePaytota(purchaseStatus: 'paid');
        [$user, $org] = $this->organizer();
        $this->payByMobileMoney($user);

        $this->actingAs($user)->get(route('subscription.approve', SubscriptionInvoice::sole()->number))
            ->assertRedirect('/dashboard/subscription')
            ->assertSessionHas('success', 'Payment received. Your Enterprise plan is now active.');

        $this->assertSame('enterprise', $org->currentPlan()->slug);
        $this->assertSame(370, $org->commissionBps());
    }

    public function test_declined_pin_returns_to_plans_and_keeps_current_plan(): void
    {
        $this->fakePaytota(purchaseStatus: 'cancelled');
        [$user, $org] = $this->organizer();
        $this->payByMobileMoney($user);

        $this->actingAs($user)->get(route('subscription.approve', SubscriptionInvoice::sole()->number))
            ->assertRedirect('/dashboard/subscription')
            ->assertSessionHas('error', 'The Mobile Money payment was declined, cancelled or timed out. Check the number and balance, then try again, or pay by card.');

        $this->assertSame('starter', $org->currentPlan()->slug);
    }

    public function test_prompt_refused_by_paytota_shows_phone_error(): void
    {
        $this->fakePaytota(promptResponse: ['status' => 'error'], purchaseStatus: 'cancelled');
        [$user, $org] = $this->organizer();

        $this->payByMobileMoney($user)
            ->assertRedirect('/dashboard/subscription')
            ->assertSessionHasErrors(['phone' => 'The Mobile Money payment was declined, cancelled or timed out. Check the number and balance, then try again, or pay by card.']);

        $this->assertSame('starter', $org->currentPlan()->slug);
    }

    public function test_mobile_money_without_phone_is_rejected(): void
    {
        [$user] = $this->organizer();

        $this->payByMobileMoney($user, ['phone' => ''])
            ->assertSessionHasErrors(['phone' => 'Enter the Mobile Money number to send the payment prompt to.']);
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_mobile_money_is_refused_when_paytota_has_it_switched_off(): void
    {
        config(['ticketflow.payments.paytota.mobile_money' => false]);
        [$user] = $this->organizer();

        $this->payByMobileMoney($user)->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('subscription_invoices', 0);
    }
}
