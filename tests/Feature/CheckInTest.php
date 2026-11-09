<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInTest extends TestCase
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

    private function eventWithIssuedTicket(): array
    {
        $event = $this->org->events()->create([
            'title' => 'Summit', 'slug' => 'summit-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 50, 'max_per_order' => 10, 'status' => 'active',
        ]);
        $order = app(OrderService::class)->create($event, ['name' => 'B', 'email' => 'b@e.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        $payment = Payment::create([
            'reference' => 'PAY-'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id,
            'provider' => 'fake', 'provider_ref' => 'FAKE', 'idempotency_key' => uniqid(),
            'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending',
        ]);
        app(PaymentService::class)->verifyAndFulfill($payment);

        return [$event, Ticket::where('order_id', $order->id)->first()];
    }

    public function test_valid_scan_marks_ticket_used(): void
    {
        [$event, $ticket] = $this->eventWithIssuedTicket();

        $res = $this->actingAs($this->owner)
            ->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $ticket->qr_token]);

        $res->assertOk()->assertJsonPath('result', 'valid');
        $this->assertSame('used', $ticket->fresh()->status);
        $this->assertSame($this->owner->id, $ticket->fresh()->checked_in_by);
    }

    public function test_second_scan_is_duplicate(): void
    {
        [$event, $ticket] = $this->eventWithIssuedTicket();

        $this->actingAs($this->owner)->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $ticket->qr_token]);
        $res = $this->actingAs($this->owner)->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $ticket->qr_token]);

        $res->assertOk()->assertJsonPath('result', 'duplicate');
        // Still used exactly once.
        $this->assertSame(1, $event->checkIns()->where('result', 'valid')->count());
    }

    public function test_wrong_event_is_rejected(): void
    {
        [, $ticket] = $this->eventWithIssuedTicket();
        $other = $this->org->events()->create([
            'title' => 'Other', 'slug' => 'other-'.uniqid(), 'status' => 'published',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX',
        ]);

        $this->actingAs($this->owner)
            ->postJson("/dashboard/check-in/{$other->slug}/scan", ['qr_token' => $ticket->qr_token])
            ->assertOk()->assertJsonPath('result', 'wrong_event');
    }

    public function test_invalid_token_is_rejected(): void
    {
        [$event] = $this->eventWithIssuedTicket();

        $this->actingAs($this->owner)
            ->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => 'deadbeef'])
            ->assertOk()->assertJsonPath('result', 'invalid');
    }

    public function test_non_staff_cannot_scan(): void
    {
        [$event, $ticket] = $this->eventWithIssuedTicket();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $ticket->qr_token])
            ->assertForbidden();

        $this->assertSame('issued', $ticket->fresh()->status);
    }

    public function test_offline_dedupe_by_client_uuid(): void
    {
        [$event, $ticket] = $this->eventWithIssuedTicket();
        $uuid = (string) Str::uuid();

        $this->actingAs($this->owner)->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $ticket->qr_token, 'client_uuid' => $uuid]);
        $this->actingAs($this->owner)->postJson("/dashboard/check-in/{$event->slug}/scan", ['qr_token' => $ticket->qr_token, 'client_uuid' => $uuid]);

        // The replayed scan did not create a second check-in row.
        $this->assertSame(1, $event->checkIns()->count());
    }
}
