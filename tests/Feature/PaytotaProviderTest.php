<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentManager;
use App\Domain\Payments\PaymentService;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class PaytotaProviderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://gate.paytota.test/api/v1/';

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->privateKey = $privateKey;

        config([
            'ticketflow.payments.paytota' => [
                'brand_id' => 'brand-123',
                'api_key' => 'test-api-key',
                'public_key' => openssl_pkey_get_details($key)['key'],
                'base_url' => self::BASE,
            ],
        ]);
    }

    private function order(): Order
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $user = User::factory()->create();
        $org = Organization::create(['owner_id' => $user->id, 'name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create([
            'title' => 'Summit', 'slug' => 'summit-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active',
        ]);

        return app(OrderService::class)->create($event, ['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '+256700000000'], [
            ['ticket_type_id' => $type->id, 'quantity' => 2],
        ]);
    }

    private function sign(string $body): string
    {
        openssl_sign($body, $signature, $this->privateKey, 'sha256WithRSAEncryption');

        return base64_encode($signature);
    }

    public function test_initiating_creates_hosted_purchase_and_returns_checkout_url(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['id' => 'pur_1', 'checkout_url' => 'https://gate.paytota.test/p/pur_1/'])]);
        config(['app.url' => 'https://tuvio.test']);
        URL::forceRootUrl('http://127.0.0.1:8000');
        $order = $this->order();

        $intent = app(PaymentService::class)->initiateForOrder($order, 'https://app.test/return', 'paytota');

        $this->assertSame('pur_1', $intent->providerRef);
        $this->assertSame('https://gate.paytota.test/p/pur_1/', $intent->redirectUrl);
        $this->assertSame('pur_1', $order->payments()->firstOrFail()->provider_ref);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['brand_id'] === 'brand-123'
            && $request['client']['email'] === 'buyer@example.com'
            && $request['client']['phone'] === '256700000000'
            && $request['client']['country'] === 'UG'
            && $request['purchase']['products'][0]['name'] === 'Summit — 2 × Regular'
            && $request['purchase']['products'][0]['price'] === 100000
            && $request['success_callback'] === 'https://tuvio.test/webhooks/paytota');
    }

    public function test_callback_url_on_a_custom_port_is_left_out_because_paytota_rejects_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['id' => 'pur_1', 'checkout_url' => 'https://gate.paytota.test/p/pur_1/'])]);
        config(['app.url' => 'http://localhost:8000']);
        $order = $this->order();

        app(PaymentService::class)->initiateForOrder($order, 'https://app.test/return', 'paytota');

        Http::assertSent(fn (Request $request) => ! isset($request['success_callback']));
    }

    public function test_initiating_throws_when_paytota_rejects_the_purchase(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::BASE.'purchases/' => Http::response(['brand_id' => ['Invalid brand.']], 400)]);
        $order = $this->order();

        $this->expectException(RuntimeException::class);

        app(PaymentService::class)->initiateForOrder($order, 'https://app.test/return', 'paytota');
    }

    public function test_verification_succeeds_only_for_paid_purchases(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'purchases/pur_paid/' => Http::response([
                'status' => 'paid',
                'purchase' => ['total' => 100000, 'currency' => 'UGX'],
                'payment' => ['fee_amount' => 250],
                'transaction_data' => ['payment_method' => 'mobile_money'],
            ]),
            self::BASE.'purchases/pur_open/' => Http::response(['status' => 'created', 'purchase' => ['total' => 10000000, 'currency' => 'UGX']]),
        ]);
        $provider = app(PaymentManager::class)->provider('paytota');

        $paid = $provider->verify('pur_paid');
        $open = $provider->verify('pur_open');

        $this->assertTrue($paid->successful);
        $this->assertSame(10000000, $paid->amountMinor);
        $this->assertSame(25000, $paid->processorFeeMinor);
        $this->assertSame('mobile_money', $paid->method);
        $this->assertFalse($open->successful);
    }

    public function test_signed_callback_fulfils_the_order(): void
    {
        Http::preventStrayRequests();
        $order = $this->order();
        Payment::create([
            'reference' => 'PAY-PT1', 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'paytota', 'provider_ref' => 'pur_1', 'idempotency_key' => 'k1',
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        Http::fake([self::BASE.'purchases/pur_1/' => Http::response(['status' => 'paid', 'purchase' => ['total' => 100000, 'currency' => 'UGX']])]);
        $body = json_encode(['id' => 'pur_1', 'event_type' => 'purchase.paid', 'status' => 'paid']);

        $response = $this->call('POST', route('webhooks.paytota'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $this->sign($body),
        ], $body);

        $response->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, $order->fresh()->tickets()->count());
    }

    public function test_paid_purchase_in_another_currency_is_refused(): void
    {
        Http::preventStrayRequests();
        $order = $this->order();
        $payment = Payment::create([
            'reference' => 'PAY-PT3', 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'paytota', 'provider_ref' => 'pur_3', 'idempotency_key' => 'k3',
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        Http::fake([self::BASE.'purchases/pur_3/' => Http::response(['status' => 'paid', 'purchase' => ['total' => 100000, 'currency' => 'USD']])]);

        try {
            app(PaymentService::class)->verifyAndFulfill($payment);
            $this->fail('A payment in the wrong currency must not be fulfilled.');
        } catch (RuntimeException $e) {
            $this->assertSame('Payment amount or currency mismatch; refusing to fulfil.', $e->getMessage());
        }

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }

    public function test_callback_with_bad_signature_is_recorded_as_invalid_and_not_fulfilled(): void
    {
        Http::preventStrayRequests();
        $order = $this->order();
        Payment::create([
            'reference' => 'PAY-PT2', 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'paytota', 'provider_ref' => 'pur_2', 'idempotency_key' => 'k2',
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        $body = json_encode(['id' => 'pur_2', 'status' => 'paid']);

        $response = $this->call('POST', route('webhooks.paytota'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $this->sign('{"id":"tampered"}'),
        ], $body);

        $response->assertOk();
        $this->assertSame('invalid', PaymentWebhook::where('provider', 'paytota')->sole()->status);
        $this->assertSame('awaiting_payment', $order->fresh()->status);
        Http::assertNothingSent();
    }
}
