<?php

namespace Tests\Feature;

use App\Domain\Ledger\LedgerService;
use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Domain\Payouts\PayoutService;
use App\Models\Organization;
use App\Models\OrganizationPayoutAccount;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PayoutFailedNotification;
use App\Notifications\PayoutPaidNotification;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayoutDisbursementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://gate.paytota.test/api/v1/';

    private const EXECUTE = 'https://gate.paytota.test/po/po_1/paytota_proxy/';

    private string $privateKey;

    private User $owner;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->privateKey = $privateKey;

        config([
            'ticketflow.payments.default' => 'fake',
            'ticketflow.commission_bps' => 500,
            'ticketflow.payout_settlement_days' => 7,
            'ticketflow.payouts.driver' => 'paytota',
            'ticketflow.payments.paytota' => [
                'brand_id' => 'brand-123',
                'api_key' => 'test-api-key',
                'public_key' => '',
                'webhook_public_key' => openssl_pkey_get_details($key)['key'],
                'base_url' => self::BASE,
                'country' => 'UG',
                'dialing_code' => '256',
            ],
        ]);

        $this->seed(ChartOfAccountsSeeder::class);
        $this->owner = User::factory()->create();
        $this->org = Organization::create(['owner_id' => $this->owner->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active', 'support_email' => 'money@acme.test']);
        $this->org->members()->create(['user_id' => $this->owner->id, 'role' => 'owner', 'accepted_at' => now()]);
    }

    /** A payout request for one settled 100,000 UGX sale (95,000 UGX organizer net). */
    private function requestedPayout(string $type = 'mobile_money'): Payout
    {
        $event = $this->org->events()->create([
            'title' => 'Past Event', 'slug' => 'past-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->subDays(10), 'currency' => 'UGX',
        ]);
        $ticketType = $event->ticketTypes()->create(['name' => 'Reg', 'kind' => 'paid', 'price_minor' => 10000000, 'currency' => 'UGX', 'quantity_total' => 100, 'max_per_order' => 10, 'status' => 'active']);
        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@e.com'], [['ticket_type_id' => $ticketType->id, 'quantity' => 1]]);
        $payment = Payment::create(['reference' => 'P'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id, 'provider' => 'fake', 'provider_ref' => 'R'.uniqid(), 'idempotency_key' => uniqid(), 'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending']);
        app(PaymentService::class)->verifyAndFulfill($payment);

        $this->org->payoutAccounts()->create([
            'type' => $type, 'provider' => $type === 'bank' ? 'Stanbic' : 'MTN', 'account_name' => 'Jane Acme',
            'account_number' => $type === 'bank' ? '9030012345678' : '256772123456', 'currency' => 'UGX', 'is_default' => true,
        ]);

        return app(PayoutService::class)->request($this->org);
    }

    private function fakePaytota(array $execute = ['status' => 'pending', 'details' => ['return_code' => '200']], int $executeStatus = 200): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'payouts/' => Http::response(['id' => 'po_1', 'type' => 'payout', 'status' => 'initialized', 'execution_url' => self::EXECUTE], 201),
            self::EXECUTE => Http::response($execute, $executeStatus),
        ]);
    }

    private function sendCallback(array $payload)
    {
        $body = json_encode($payload);
        openssl_sign($body, $signature, $this->privateKey, 'sha256WithRSAEncryption');

        return $this->call('POST', '/webhooks/paytota', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => base64_encode($signature)], content: $body);
    }

    private function approve(Payout $payout, array $data = [])
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/payouts/{$payout->id}/approve", ['password' => 'password', ...$data]);
    }

    public function test_approving_a_mobile_money_payout_sends_it_through_paytota(): void
    {
        $this->fakePaytota();
        $payout = $this->requestedPayout();

        $this->approve($payout)->assertSessionHas('success');

        $payout->refresh();
        $this->assertSame('processing', $payout->status);
        $this->assertSame('paytota', $payout->provider);
        $this->assertSame('po_1', $payout->provider_ref);

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE.'payouts/'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['brand_id'] === 'brand-123'
            && $request['client']['phone'] === '256772123456'
            && $request['client']['email'] === 'money@acme.test'
            && $request['payment'] === ['currency' => 'UGX', 'amount' => '95000', 'description' => "TUVIO payout {$payout->reference} to Acme"]
            && str_starts_with($request['reference'], $payout->reference.'-'));
        Http::assertSent(fn (Request $request) => $request->url() === self::EXECUTE && $request['payout_type'] === 'mobile');
    }

    public function test_success_callback_settles_the_payout_once(): void
    {
        Notification::fake();
        $this->fakePaytota();
        $payout = $this->requestedPayout();
        $this->approve($payout);

        $this->sendCallback(['id' => 'po_1', 'type' => 'payout', 'event_type' => 'payout.success', 'status' => 'success'])->assertOk();
        $this->sendCallback(['id' => 'po_1', 'type' => 'payout', 'event_type' => 'payout.success', 'status' => 'success'])->assertOk();

        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame(0, app(LedgerService::class)->organizerPayable($this->org, 'UGX')->balanceMinor());
        Notification::assertSentToTimes($this->owner, PayoutPaidNotification::class, 1);
    }

    public function test_error_callback_fails_the_payout_and_returns_the_funds(): void
    {
        Notification::fake();
        $this->fakePaytota();
        $payout = $this->requestedPayout();
        $this->approve($payout);

        $this->sendCallback(['id' => 'po_1', 'type' => 'payout', 'event_type' => 'payout.error', 'status' => 'error'])->assertOk();

        $this->assertSame('failed', $payout->fresh()->status);
        $this->assertSame(9500000, app(PayoutService::class)->availableMinor($this->org));
        Notification::assertSentTo($this->owner, PayoutFailedNotification::class);
    }

    public function test_insufficient_balance_keeps_the_payout_ready_to_retry(): void
    {
        $this->fakePaytota(['status' => 'error', 'error' => ['code' => 'insufficient_funds', 'message' => 'Insufficient funds to proceed with operation.']], 400);
        $payout = $this->requestedPayout();

        $this->approve($payout)->assertSessionHas('error');

        $payout->refresh();
        $this->assertSame('requested', $payout->status);
        $this->assertNull($payout->provider_ref);
        $this->assertSame('Insufficient funds to proceed with operation.', $payout->notes);
        $this->assertSame(0, app(PayoutService::class)->availableMinor($this->org));
    }

    public function test_lost_connection_while_executing_keeps_the_payout_processing(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'payouts/' => Http::response(['id' => 'po_1', 'execution_url' => self::EXECUTE], 201),
            self::EXECUTE => Http::failedConnection(),
        ]);
        $payout = $this->requestedPayout();

        $this->approve($payout)->assertSessionHas('error');

        $this->assertSame('processing', $payout->fresh()->status);
        $this->assertSame('po_1', $payout->fresh()->provider_ref);
    }

    public function test_status_check_settles_a_processing_payout(): void
    {
        $this->fakePaytota();
        Http::fake([self::BASE.'payouts/po_1/' => Http::response(['id' => 'po_1', 'status' => 'success'])]);
        $payout = $this->requestedPayout();
        $this->approve($payout);

        $this->actingAs(User::factory()->admin()->create())->post("/admin/payouts/{$payout->id}/refresh")->assertSessionHas('success');

        $this->assertSame('paid', $payout->fresh()->status);
    }

    public function test_an_execution_url_on_another_host_never_receives_the_api_key(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'payouts/' => Http::response(['id' => 'po_1', 'execution_url' => 'https://evil.test/po/po_1/'], 201),
            self::EXECUTE => Http::response(['status' => 'pending']),
        ]);

        $this->approve($this->requestedPayout())->assertSessionHas('success');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.test'));
        Http::assertSent(fn (Request $request) => $request->url() === self::EXECUTE);
    }

    public function test_callback_with_a_bad_signature_is_ignored(): void
    {
        $this->fakePaytota();
        $payout = $this->requestedPayout();
        $this->approve($payout);

        $this->postJson('/webhooks/paytota', ['id' => 'po_1', 'type' => 'payout', 'status' => 'success'], ['X-Signature' => base64_encode('forged')])->assertOk();

        $this->assertSame('processing', $payout->fresh()->status);
        $this->assertDatabaseHas(PaymentWebhook::class, ['status' => 'invalid', 'error' => 'Bad signature']);
    }

    public function test_approving_needs_the_admin_password(): void
    {
        Http::preventStrayRequests();
        $payout = $this->requestedPayout();

        $this->approve($payout, ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertSame('requested', $payout->fresh()->status);
    }

    public function test_bank_payouts_are_recorded_manually_with_a_reference(): void
    {
        Http::preventStrayRequests();
        $payout = $this->requestedPayout('bank');

        $this->approve($payout)->assertSessionHasErrors('provider_ref');
        $this->approve($payout, ['provider_ref' => 'EFT-778'])->assertSessionHas('success');

        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('EFT-778', $payout->fresh()->provider_ref);
    }

    public function test_rejecting_a_payout_returns_the_funds(): void
    {
        $payout = $this->requestedPayout();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/payouts/{$payout->id}/reject", ['reason' => 'Number not registered to the organizer.'])
            ->assertSessionHas('success');

        $this->assertSame('failed', $payout->fresh()->status);
        $this->assertSame(9500000, app(PayoutService::class)->availableMinor($this->org));
    }

    public function test_mobile_money_accounts_are_validated_and_normalized(): void
    {
        $this->actingAs($this->owner)
            ->post('/dashboard/payouts/accounts', ['type' => 'mobile_money', 'provider' => 'MTN', 'account_name' => 'Jane', 'account_number' => '12345'])
            ->assertSessionHasErrors('account_number');

        $this->actingAs($this->owner)
            ->post('/dashboard/payouts/accounts', ['type' => 'mobile_money', 'provider' => 'Airtel', 'account_name' => 'Jane', 'account_number' => '0752 123 456'])
            ->assertSessionHasNoErrors();

        $this->assertSame('256752123456', $this->org->payoutAccounts()->sole()->account_number);
    }

    public function test_organizers_cannot_remove_another_organizations_account(): void
    {
        $other = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Other', 'slug' => 'other', 'default_currency' => 'UGX', 'status' => 'active']);
        $account = $other->payoutAccounts()->create(['type' => 'mobile_money', 'provider' => 'MTN', 'account_name' => 'X', 'account_number' => '256772000000', 'currency' => 'UGX']);

        $this->actingAs($this->owner)->delete("/dashboard/payouts/accounts/{$account->id}")->assertNotFound();

        $this->assertModelExists($account);
    }

    public function test_an_account_with_a_payout_in_progress_cannot_be_removed(): void
    {
        $payout = $this->requestedPayout();
        $account = OrganizationPayoutAccount::findOrFail($payout->organization_payout_account_id);

        $this->actingAs($this->owner)->delete("/dashboard/payouts/accounts/{$account->id}")->assertSessionHas('error');

        $this->assertModelExists($account);
    }
}
