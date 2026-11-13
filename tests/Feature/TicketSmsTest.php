<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketSmsTest extends TestCase
{
    use RefreshDatabase;

    private const AT_URL = 'https://api.sandbox.africastalking.com/version1/messaging';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'ticketflow.payments.default' => 'fake',
            'ticketflow.sms.driver' => 'africastalking',
            'ticketflow.sms.africastalking' => ['username' => 'sandbox', 'api_key' => 'at-key', 'sender_id' => 'TUVIO', 'sandbox' => true],
        ]);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function paidOrder(?string $phone = '0772123456'): Order
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create([
            'title' => 'Kampala Jazz Night', 'slug' => 'jazz', 'status' => 'published', 'type' => 'physical',
            'visibility' => 'public', 'start_at' => '2026-12-12 18:00:00', 'timezone' => 'Africa/Kampala', 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active',
        ]);
        $order = app(OrderService::class)->create($event, ['name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => $phone], [
            ['ticket_type_id' => $type->id, 'quantity' => 2],
        ]);
        $payment = Payment::create([
            'reference' => 'PAY-SMS', 'purpose' => 'ticket_order', 'order_id' => $order->id, 'provider' => 'fake',
            'provider_ref' => 'FAKE-SMS', 'idempotency_key' => 'ksms', 'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        app(PaymentService::class)->verifyAndFulfill($payment);

        return $order->fresh();
    }

    public function test_buyer_gets_an_sms_with_a_link_to_their_tickets_after_payment(): void
    {
        Http::fake([self::AT_URL => Http::response(['SMSMessageData' => ['Message' => 'Sent to 1/1', 'Recipients' => [
            ['statusCode' => 101, 'number' => '+256772123456', 'status' => 'Success', 'cost' => 'UGX 25.0000', 'messageId' => 'ATXid_1'],
        ]]], 201)]);

        $order = $this->paidOrder();

        Http::assertSent(function (Request $request) use ($order) {
            return $request->url() === self::AT_URL
                && $request->hasHeader('apiKey', 'at-key')
                && $request['username'] === 'sandbox'
                && $request['from'] === 'TUVIO'
                && $request['to'] === '+256772123456'
                && str_contains($request['message'], '2 tickets for Kampala Jazz Night (12 Dec, 6:00pm)')
                && str_contains($request['message'], route('checkout.complete', $order->reference))
                && strlen($request['message']) <= 160;
        });
        $this->assertSame('sent', $order->meta['sms']['status']);
        $this->assertSame('ATXid_1', $order->meta['sms']['message_id']);
        $this->assertSame('paid', $order->status);
    }

    public function test_a_rejected_sms_is_recorded_and_the_tickets_are_still_issued(): void
    {
        Http::fake([self::AT_URL => Http::response(['SMSMessageData' => ['Message' => 'Sent to 0/1', 'Recipients' => [
            ['statusCode' => 405, 'number' => '+256772123456', 'status' => 'InsufficientBalance', 'cost' => '0', 'messageId' => 'None'],
        ]]], 201)]);

        $order = $this->paidOrder();

        $this->assertSame('paid', $order->status);
        $this->assertSame(2, $order->tickets()->count());
        $this->assertSame('failed', $order->meta['sms']['status']);
        $this->assertSame('InsufficientBalance', $order->meta['sms']['error']);
    }

    public function test_no_sms_is_sent_without_a_phone_number(): void
    {
        Http::fake();

        $order = $this->paidOrder(phone: null);

        Http::assertNothingSent();
        $this->assertArrayNotHasKey('sms', $order->meta ?? []);
        $this->assertSame(2, $order->tickets()->count());
    }

    public function test_log_driver_sends_nothing_over_the_network(): void
    {
        config(['ticketflow.sms.driver' => 'log']);
        Http::fake();

        $order = $this->paidOrder();

        Http::assertNothingSent();
        $this->assertSame('sent', $order->meta['sms']['status']);
    }
}
