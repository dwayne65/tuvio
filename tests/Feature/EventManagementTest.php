<?php

namespace Tests\Feature;

use App\Domain\Subscriptions\SubscriptionService;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);

        return [$user, Organization::first()];
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'African Business Summit 2026',
            'type' => 'physical',
            'visibility' => 'public',
            'start_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'venue_name' => 'Serena Hotel',
            'city' => 'Kampala',
            'ticket_types' => [
                ['name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'quantity_total' => 100, 'max_per_order' => 5],
            ],
        ], $overrides);
    }

    public function test_organizer_can_create_event_as_draft(): void
    {
        [$user] = $this->organizer();

        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload())
            ->assertRedirect();

        $event = Event::first();
        $this->assertNotNull($event);
        $this->assertSame('draft', $event->status);
        $this->assertSame(1, $event->ticketTypes()->count());
    }

    public function test_event_creation_persists_all_details_ticket_types_and_cover(): void
    {
        [$user] = $this->organizer();
        $category = EventCategory::create([
            'name' => 'Conference', 'slug' => 'conference', 'icon' => 'briefcase',
            'color' => '#7557E8', 'sort' => 1, 'is_active' => true,
        ]);
        Storage::fake('public');

        $payload = $this->eventPayload([
            'description' => 'A premium leadership gathering.',
            'event_category_id' => $category->id,
            'end_at' => now()->addMonth()->addHours(5)->format('Y-m-d\TH:i'),
            'timezone' => 'Africa/Kampala',
            'venue_address' => '12 Acacia Avenue',
            'refund_policy' => 'Tickets are refundable up to 7 days before the event.',
            'ticket_types' => [
                ['name' => 'VIP', 'kind' => 'paid', 'price_minor' => 12500000, 'quantity_total' => 50, 'max_per_order' => 4],
                ['name' => 'Community pass', 'kind' => 'free', 'price_minor' => 0, 'quantity_total' => null, 'max_per_order' => 1],
            ],
            'cover' => UploadedFile::fake()->image('summit-cover.jpg'),
        ]);

        $this->actingAs($user)->post('/dashboard/events', $payload)->assertRedirect();

        $event = Event::with('ticketTypes')->firstOrFail();
        $this->assertSame('A premium leadership gathering.', $event->description);
        $this->assertSame($category->id, $event->event_category_id);
        $this->assertSame('12 Acacia Avenue', $event->venue_address);
        $this->assertSame('Africa/Kampala', $event->timezone);
        $this->assertSame(2, $event->ticketTypes->count());
        $this->assertSame(12500000, $event->ticketTypes->firstWhere('name', 'VIP')->price_minor);
        $this->assertSame('free', $event->ticketTypes->firstWhere('name', 'Community pass')->kind);
        $this->assertNotNull($event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
    }

    public function test_publish_blocked_without_active_subscription(): void
    {
        [$user, $org] = $this->organizer();
        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload());
        $event = Event::first();

        $this->actingAs($user)->post("/dashboard/events/{$event->slug}/publish")
            ->assertSessionHas('error');

        $this->assertSame('draft', $event->fresh()->status);
    }

    public function test_publish_succeeds_with_subscription(): void
    {
        [$user, $org] = $this->organizer();
        $plan = SubscriptionPlan::create([
            'name' => 'Starter', 'slug' => 'starter', 'price_minor' => 0, 'currency' => 'UGX',
            'interval' => 'monthly', 'trial_days' => 0, 'event_limit' => 3, 'is_active' => true,
        ]);
        app(SubscriptionService::class)->subscribe($org, $plan);

        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload());
        $event = Event::first();

        $this->actingAs($user)->post("/dashboard/events/{$event->slug}/publish")
            ->assertSessionHas('success');

        $this->assertSame('published', $event->fresh()->status);
        $this->assertNotNull($event->fresh()->published_at);
    }

    public function test_only_members_can_manage_events(): void
    {
        [, $org] = $this->organizer();
        $event = $org->events()->create([
            'title' => 'Private', 'slug' => 'private-x', 'status' => 'draft',
            'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addWeek(), 'currency' => 'UGX',
        ]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get("/dashboard/events/{$event->slug}/edit")->assertForbidden();
    }

    public function test_cannot_delete_ticket_type_with_sales(): void
    {
        [$user, $org] = $this->organizer();
        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload());
        $event = Event::first();
        $type = $event->ticketTypes()->first();
        $type->update(['quantity_sold' => 2]);

        $this->actingAs($user)
            ->delete("/dashboard/events/{$event->slug}/ticket-types/{$type->id}")
            ->assertStatus(422);
    }

    public function test_event_creation_saves_ticket_tiers_perks_and_sales_window(): void
    {
        [$user] = $this->organizer();

        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload([
            'ticket_types' => [
                ['tier' => 'general', 'name' => 'General Admission', 'kind' => 'paid', 'price_minor' => 5000000, 'max_per_order' => 5],
                [
                    'tier' => 'vvip', 'name' => 'VVIP Table', 'kind' => 'paid', 'price_minor' => 50000000, 'max_per_order' => 2,
                    'description' => 'Best seats in the house', 'perks' => ['Backstage access', ' Meet & greet ', ''],
                    'sales_start_at' => now()->addDay()->format('Y-m-d\TH:i'), 'sales_end_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                ],
                ['tier' => 'other', 'name' => 'Community pass', 'kind' => 'free', 'price_minor' => 9900, 'max_per_order' => 1],
            ],
        ]))->assertSessionHasNoErrors();

        $types = Event::firstOrFail()->ticketTypes->keyBy('name');
        $this->assertSame('vvip', $types['VVIP Table']->tier);
        $this->assertSame(['Backstage access', 'Meet & greet'], $types['VVIP Table']->perks);
        $this->assertNotNull($types['VVIP Table']->sales_start_at);
        $this->assertSame('other', $types['Community pass']->tier);
        $this->assertSame(0, $types['Community pass']->price_minor);
    }

    public function test_organizer_can_add_and_edit_a_tiered_ticket_type(): void
    {
        [$user] = $this->organizer();
        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload());
        $event = Event::firstOrFail();
        $payload = ['tier' => 'vip', 'name' => 'VIP', 'kind' => 'paid', 'price_minor' => 15000000, 'max_per_order' => 4, 'status' => 'active', 'perks' => ['Fast-track entry']];

        $this->actingAs($user)->post("/dashboard/events/{$event->slug}/ticket-types", $payload)->assertSessionHasNoErrors();
        $type = $event->ticketTypes()->where('name', 'VIP')->firstOrFail();

        $this->actingAs($user)
            ->patch("/dashboard/events/{$event->slug}/ticket-types/{$type->id}", [...$payload, 'name' => 'VIP Lounge', 'perks' => ['Fast-track entry', 'Reserved seating']])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $type->refresh();
        $this->assertSame('VIP Lounge', $type->name);
        $this->assertSame(['Fast-track entry', 'Reserved seating'], $type->perks);
    }

    public function test_ticket_type_rejects_unknown_tier_and_backwards_sales_window(): void
    {
        [$user] = $this->organizer();
        $this->actingAs($user)->post('/dashboard/events', $this->eventPayload());
        $event = Event::firstOrFail();

        $this->actingAs($user)->post("/dashboard/events/{$event->slug}/ticket-types", [
            'tier' => 'platinum-plus', 'name' => 'X', 'kind' => 'paid', 'price_minor' => 100, 'max_per_order' => 1, 'status' => 'active',
            'sales_start_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'sales_end_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors(['tier', 'sales_end_at']);
    }
}
