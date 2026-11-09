<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.payments.default' => 'fake']);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->owner = User::factory()->create();
        $this->org = Organization::create(['owner_id' => $this->owner->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $this->org->members()->create(['user_id' => $this->owner->id, 'role' => 'owner', 'accepted_at' => now()]);
    }

    /** @return array{Event, Collection<int, Ticket>} */
    private function eventWithTickets(int $quantity = 2): array
    {
        $event = $this->org->events()->create([
            'title' => 'Summit', 'slug' => 'summit-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addDay(), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'VIP', 'tier' => 'vip', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 50, 'max_per_order' => 10, 'status' => 'active',
        ]);
        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@e.com'], [['ticket_type_id' => $type->id, 'quantity' => $quantity]]);
        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'fake', 'provider_ref' => 'FAKE', 'idempotency_key' => uniqid(),
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        app(PaymentService::class)->verifyAndFulfill($payment);

        return [$event, Ticket::where('order_id', $order->id)->get()];
    }

    public function test_organizer_assigns_gate_staff_who_can_scan_but_not_manage(): void
    {
        [$event, $tickets] = $this->eventWithTickets();
        $scanner = User::factory()->create(['email' => 'Gate.Keeper@example.com']);

        $this->actingAs($this->owner)->post("/dashboard/check-in/{$event->slug}/gates", ['name' => 'Main gate'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)
            ->post("/dashboard/check-in/{$event->slug}/staff", ['email' => 'gate.keeper@example.com', 'role' => 'scanner'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(AuditLog::where('action', 'event.staff_assigned')->exists());

        $this->actingAs($scanner)->get('/dashboard/check-in')
            ->assertInertia(fn ($page) => $page->component('Dashboard/CheckIn/Index', false)
                ->where('events.0.slug', $event->slug)
                ->where('events.0.access', 'scanner')
                ->where('events.0.can_manage', false));

        $this->actingAs($scanner)
            ->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $tickets[0]->qr_token, 'gate' => 'Main gate'])
            ->assertOk()->assertJsonPath('result', 'valid');

        $this->actingAs($scanner)->get("/dashboard/check-in/{$event->slug}/setup")->assertForbidden();
        $this->actingAs($scanner)->get("/dashboard/check-in/{$event->slug}/live")->assertForbidden();
        $this->actingAs($scanner)->get("/dashboard/events/{$event->slug}/edit")->assertForbidden();
    }

    public function test_gate_managers_see_the_live_dashboard_with_real_breakdowns(): void
    {
        [$event, $tickets] = $this->eventWithTickets(3);
        $manager = User::factory()->create();
        $event->staff()->create(['user_id' => $manager->id, 'role' => 'manager']);

        $this->actingAs($manager)->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $tickets[0]->qr_token, 'gate' => 'North']);
        $this->actingAs($manager)->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $tickets[0]->qr_token, 'gate' => 'North']);

        $this->actingAs($manager)->get("/dashboard/check-in/{$event->slug}/live")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard/CheckIn/Live', false)
                ->where('stats.sold', 3)
                ->where('stats.checked_in', 1)
                ->where('stats.percent', 33)
                ->where('stats.scans.duplicate', 1)
                ->where('stats.by_gate.0.gate', 'North')
                ->where('stats.by_type.0.name', 'VIP')
                ->where('stats.by_type.0.checked_in', 1)
                ->where('canManage', false));
    }

    public function test_staff_cannot_be_added_without_an_account_or_twice_as_team_member(): void
    {
        [$event] = $this->eventWithTickets(1);

        $this->actingAs($this->owner)
            ->post("/dashboard/check-in/{$event->slug}/staff", ['email' => 'nobody@example.com', 'role' => 'scanner'])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->owner)
            ->post("/dashboard/check-in/{$event->slug}/staff", ['email' => $this->owner->email, 'role' => 'scanner'])
            ->assertSessionHasErrors('email');
    }

    public function test_offline_manifest_holds_hashes_not_tokens_and_is_private(): void
    {
        [$event, $tickets] = $this->eventWithTickets(1);

        $manifest = $this->actingAs($this->owner)->getJson("/dashboard/check-in/{$event->slug}/manifest")->assertOk();

        $manifest->assertJsonPath('tickets.0.h', hash('sha256', $tickets[0]->qr_token))
            ->assertJsonPath('tickets.0.s', 'valid');
        $this->assertStringNotContainsString($tickets[0]->qr_token, $manifest->getContent());

        $this->actingAs(User::factory()->create())->getJson("/dashboard/check-in/{$event->slug}/manifest")->assertForbidden();
    }

    public function test_offline_scans_sync_in_gate_order_idempotently(): void
    {
        [$event, $tickets] = $this->eventWithTickets(2);
        $firstAt = now()->subMinutes(30)->startOfSecond();
        $scans = [
            // Device B scanned the same ticket later — it must come back as a duplicate.
            ['client_uuid' => (string) Str::uuid(), 'qr_token' => $tickets[0]->qr_token, 'scanned_at' => now()->subMinutes(10)->toIso8601String(), 'gate' => 'South'],
            ['client_uuid' => (string) Str::uuid(), 'qr_token' => $tickets[0]->qr_token, 'scanned_at' => $firstAt->toIso8601String(), 'gate' => 'North'],
            ['client_uuid' => (string) Str::uuid(), 'code' => strtolower($tickets[1]->code), 'scanned_at' => now()->subMinutes(5)->toIso8601String()],
            ['client_uuid' => (string) Str::uuid(), 'qr_token' => 'x', 'scanned_at' => now()->subDays(30)->toIso8601String()],
        ];

        $response = $this->actingAs($this->owner)->postJson("/dashboard/check-in/{$event->slug}/sync", ['device_id' => 'phone-1', 'scans' => $scans])->assertOk();
        $results = collect($response->json('results'))->keyBy('client_uuid');

        $this->assertSame('valid', $results[$scans[1]['client_uuid']]['result']);
        $this->assertSame('duplicate', $results[$scans[0]['client_uuid']]['result']);
        $this->assertSame('valid', $results[$scans[2]['client_uuid']]['result']);
        $this->assertSame('rejected', $results[$scans[3]['client_uuid']]['result']);
        $this->assertTrue($tickets[0]->fresh()->used_at->equalTo($firstAt));

        // Re-sending the same batch after a dropped connection changes nothing.
        $this->actingAs($this->owner)->postJson("/dashboard/check-in/{$event->slug}/sync", ['device_id' => 'phone-1', 'scans' => $scans])->assertOk();
        // Three scans logged once each; the out-of-range scan is refused without a record.
        $this->assertSame(3, $event->checkIns()->count());
    }

    public function test_tickets_can_be_checked_in_by_typed_code(): void
    {
        [$event, $tickets] = $this->eventWithTickets(1);

        $this->actingAs($this->owner)
            ->postJson("/dashboard/check-in/{$event->slug}/scan", ['code' => ' '.strtolower($tickets[0]->code)])
            ->assertOk()->assertJsonPath('result', 'valid');
        $this->actingAs($this->owner)
            ->postJson("/dashboard/check-in/{$event->slug}/scan", ['code' => 'TKT-NOPE'])
            ->assertOk()->assertJsonPath('result', 'invalid');
    }
}
