<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Tickets\TicketIssuer;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UssdTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://gate.paytota.test/api/v1/';

    private const MSISDN = '256700000000';

    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        config([
            'ticketflow.demo_mode' => false,
            'ticketflow.ussd.public_key' => openssl_pkey_get_details($this->privateKey)['key'],
            'ticketflow.payments.default' => 'paytota',
            'ticketflow.payments.paytota' => [
                'brand_id' => 'brand-123', 'api_key' => 'test-api-key', 'public_key' => null, 'base_url' => self::BASE,
                'dialing_code' => '256', 'mobile_money' => true,
            ],
        ]);
    }

    private function organization(): Organization
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $this->onPaidPlan($org, planAttributes: ['ussd_event_limit' => null]);

        return $org;
    }

    private function ticketType(): TicketType
    {
        $event = $this->organization()->events()->create([
            'title' => 'Summit', 'slug' => 'summit', 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
            'sell_on_ussd' => true,
        ]);

        return $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active',
        ]);
    }

    private function campaign(): Campaign
    {
        return $this->organization()->campaigns()->create(['title' => 'Build a well', 'slug' => 'well', 'currency' => 'UGX', 'status' => 'active', 'raised_minor' => 0]);
    }

    /** Send one keypress the way the gateway does: a signed JSON body. */
    private function dial(string $state, string $message = '', string $msisdn = self::MSISDN, string $session = 'sess-1'): TestResponse
    {
        $payload = ['shortcode' => '165', 'msisdn' => $msisdn, 'mccmnc' => '64101', 'sessionid' => $session, 'state' => $state, 'message' => $message];
        openssl_sign(json_encode($payload), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $this->withHeader('Signature', base64_encode($signature))->postJson('/webhooks/ussd', $payload);
    }

    /** @param  list<string>  $keypresses */
    private function dialSequence(array $keypresses): TestResponse
    {
        $response = $this->dial('START', '*165#');
        foreach ($keypresses as $keypress) {
            $response = $this->dial('CONTINUE', $keypress);
        }

        return $response;
    }

    private function fakeMobileMoneyPrompt(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::BASE.'purchases/' => Http::response(['id' => 'pur_mm', 'checkout_url' => 'https://gate.paytota.test/p/pur_mm/']),
            'https://gate.paytota.test/p/pur_mm/*' => Http::response(['status' => 'pending']),
        ]);
    }

    public function test_menu_lists_only_events_switched_on_for_ussd_within_each_plan_limit(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $event = function (Organization $org, string $title, int $days, bool $onUssd): void {
            $org->events()->create([
                'title' => $title, 'slug' => Str::slug($title), 'status' => 'published', 'type' => 'physical',
                'visibility' => 'public', 'start_at' => now()->addDays($days), 'currency' => 'UGX', 'sell_on_ussd' => $onUssd,
            ])->ticketTypes()->create([
                'name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
                'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active',
            ]);
        };
        $professional = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Pro', 'slug' => 'pro', 'default_currency' => 'UGX', 'status' => 'active']);
        $this->onPaidPlan($professional, planAttributes: ['ussd_event_limit' => 1]);
        $event($professional, 'Soonest Gig', 1, true);
        $event($professional, 'Over Limit Gig', 2, true);
        $event($professional, 'Web Only Gig', 3, false);
        // Downgraded to a plan without USSD: events switched on earlier are no longer offered.
        $starter = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Free', 'slug' => 'free', 'default_currency' => 'UGX', 'status' => 'active']);
        $this->onPaidPlan($starter, planAttributes: ['ussd_event_limit' => 0]);
        $event($starter, 'Starter Gig', 1, true);

        $this->dialSequence(['1'])
            ->assertJsonPath('state', 'CONTINUE')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Soonest Gig')
                && ! str_contains($message, 'Over Limit Gig')
                && ! str_contains($message, 'Web Only Gig')
                && ! str_contains($message, 'Starter Gig'));
    }

    public function test_request_with_invalid_signature_is_rejected_with_401(): void
    {
        $this->withHeader('Signature', base64_encode('forged'))
            ->postJson('/webhooks/ussd', ['msisdn' => self::MSISDN, 'sessionid' => 'sess-1', 'state' => 'START', 'message' => ''])
            ->assertUnauthorized();
    }

    public function test_new_session_shows_main_menu(): void
    {
        $this->dial('START', '*165#')->assertExactJson([
            'msisdn' => self::MSISDN,
            'sessionid' => 'sess-1',
            'state' => 'CONTINUE',
            'message' => "Welcome to TUVIO\n1. Buy tickets\n2. My tickets\n3. Contribute",
        ]);
    }

    public function test_buying_tickets_reserves_order_for_caller_and_sends_mobile_money_prompt(): void
    {
        $this->fakeMobileMoneyPrompt();
        $type = $this->ticketType();

        $this->dialSequence(['1', '1', '1', '2', 'Jane Doe', '1'])
            ->assertJsonPath('state', 'END')
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Approve the Mobile Money prompt of UGX 100,000 with your PIN.'));

        $order = Order::sole();
        $this->assertSame(['Jane Doe', null, '+256700000000', 10000000, 'awaiting_payment'], [$order->buyer_name, $order->buyer_email, $order->buyer_phone, $order->total_minor, $order->status]);
        $this->assertSame(2, $type->fresh()->quantity_reserved);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/'
            && $request['client']['phone'] === self::MSISDN
            && $request['payment_method_whitelist'] === ['paytota_proxy']);
    }

    public function test_quantity_above_ticket_limit_asks_again(): void
    {
        $this->ticketType();

        $this->dialSequence(['1', '1', '1', '6'])
            ->assertJsonPath('state', 'CONTINUE')
            ->assertJsonPath('message', 'Enter a number from 1 to 5.');
    }

    public function test_confirming_order_while_mobile_money_is_disabled_reserves_nothing(): void
    {
        config(['ticketflow.payments.paytota.mobile_money' => false]);
        $type = $this->ticketType();

        $this->dialSequence(['1', '1', '1', '2', 'Jane Doe', '1'])
            ->assertJsonPath('state', 'END')
            ->assertJsonPath('message', 'Mobile Money is not available right now. Please try again later or buy on tuvio.org.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $type->fresh()->quantity_reserved);
    }

    public function test_my_tickets_lists_codes_of_orders_placed_with_this_number_on_the_web(): void
    {
        $type = $this->ticketType();
        $order = app(OrderService::class)->create($type->event, ['name' => 'Jane', 'email' => 'jane@example.com', 'phone' => '0700 000000'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        app(TicketIssuer::class)->issueForOrder($order);
        $code = $order->tickets()->value('code');

        $this->dialSequence(['2'])
            ->assertJsonPath('state', 'END')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, "Regular: {$code}"));
    }

    public function test_my_tickets_hides_tickets_bought_with_another_number(): void
    {
        $type = $this->ticketType();
        $order = app(OrderService::class)->create($type->event, ['name' => 'Ann', 'email' => 'ann@example.com', 'phone' => '+256711111111'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        app(TicketIssuer::class)->issueForOrder($order);

        $this->dialSequence(['2'])->assertJsonPath('message', 'No upcoming tickets were found for this number.');
    }

    public function test_contributing_records_caller_phone_and_sends_mobile_money_prompt(): void
    {
        $this->fakeMobileMoneyPrompt();
        $this->campaign();

        $this->dialSequence(['3', '1', '20,000', 'Jane Doe', '1'])
            ->assertJsonPath('state', 'END')
            ->assertJsonPath('message', 'Approve the Mobile Money prompt of UGX 20,000 with your PIN. Thank you for your support.');

        $contribution = Contribution::sole();
        $this->assertSame(['Jane Doe', '+256700000000', 2000000], [$contribution->contributor_name, $contribution->contributor_phone, $contribution->amount_minor]);
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'purchases/' && $request['client']['phone'] === self::MSISDN);
    }

    public function test_contribution_below_minimum_asks_again(): void
    {
        $this->campaign();

        $this->dialSequence(['3', '1', '500'])
            ->assertJsonPath('state', 'CONTINUE')
            ->assertJsonPath('message', 'Enter a whole amount of at least 1,000.');
    }

    public function test_session_continued_from_another_number_starts_over_at_main_menu(): void
    {
        $this->ticketType();
        $this->dialSequence(['1']);

        $this->dial('CONTINUE', '1', msisdn: '256711111111')
            ->assertJsonPath('message', "Welcome to TUVIO\n1. Buy tickets\n2. My tickets\n3. Contribute");
    }
}
