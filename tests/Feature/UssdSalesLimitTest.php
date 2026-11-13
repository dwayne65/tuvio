<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UssdSalesLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{0: User, 1: Organization} */
    private function organizer(?int $ussdEventLimit): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);
        $org = Organization::sole();
        $this->onPaidPlan($org, planAttributes: ['ussd_event_limit' => $ussdEventLimit]);

        return [$user, $org];
    }

    private function event(Organization $org, int $daysAway = 30): Event
    {
        return $org->events()->create([
            'title' => 'Gig', 'slug' => 'gig-'.uniqid(), 'status' => 'published', 'type' => 'physical',
            'visibility' => 'public', 'start_at' => now()->addDays($daysAway), 'currency' => 'UGX',
        ]);
    }

    public function test_professional_limit_allows_three_upcoming_events_on_ussd_at_a_time(): void
    {
        [$user, $org] = $this->organizer(3);
        $this->event($org, -1)->update(['sell_on_ussd' => true]);
        $events = collect(range(1, 4))->map(fn () => $this->event($org));

        foreach ($events->take(3) as $event) {
            $this->actingAs($user)->post("/dashboard/events/{$event->slug}/ussd", ['sell_on_ussd' => true])->assertSessionHas('success');
        }
        $this->actingAs($user)->post("/dashboard/events/{$events[3]->slug}/ussd", ['sell_on_ussd' => true])
            ->assertSessionHas('error', 'Your plan allows USSD sales for 3 upcoming events at a time. Switch another event off, or upgrade your plan.');

        $this->assertFalse($events[3]->fresh()->sell_on_ussd);

        $this->actingAs($user)->post("/dashboard/events/{$events[0]->slug}/ussd", ['sell_on_ussd' => false]);
        $this->actingAs($user)->post("/dashboard/events/{$events[3]->slug}/ussd", ['sell_on_ussd' => true])->assertSessionHas('success');
        $this->assertTrue($events[3]->fresh()->sell_on_ussd);
    }

    public function test_unlimited_plan_puts_any_number_of_events_on_ussd(): void
    {
        [$user, $org] = $this->organizer(null);

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->post("/dashboard/events/{$this->event($org)->slug}/ussd", ['sell_on_ussd' => true])->assertSessionHas('success');
        }

        $this->assertSame(5, $org->events()->where('sell_on_ussd', true)->count());
    }

    public function test_plan_without_ussd_cannot_switch_it_on(): void
    {
        [$user, $org] = $this->organizer(0);
        $event = $this->event($org);

        $this->actingAs($user)->post("/dashboard/events/{$event->slug}/ussd", ['sell_on_ussd' => true])
            ->assertSessionHas('error', 'Your plan does not include USSD ticket sales. Upgrade your plan to sell on USSD.');

        $this->assertFalse($event->fresh()->sell_on_ussd);
    }
}
