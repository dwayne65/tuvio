<?php

namespace Tests\Feature;

use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_and_privacy_pages_render(): void
    {
        $this->get('/terms')->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Terms', false));
        $this->get('/privacy')->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Privacy', false));
    }

    public function test_home_spotlight_lists_every_live_event_featured_first(): void
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $event = fn (string $slug, array $attrs = []) => $org->events()->create([
            'title' => $slug, 'slug' => $slug, 'status' => 'published', 'type' => 'physical', 'visibility' => 'public',
            'start_at' => now()->addWeek(), 'currency' => 'UGX', ...$attrs,
        ]);
        $event('later', ['start_at' => now()->addMonth()]);
        $event('soon', ['start_at' => now()->addDay()]);
        $event('headliner', ['start_at' => now()->addMonths(2), 'is_featured' => true]);
        $event('happening-now', ['start_at' => now()->subHour(), 'end_at' => now()->addHours(3)]);
        $event('finished', ['start_at' => now()->subDays(2), 'end_at' => now()->subDay()]);
        $event('draft', ['status' => 'draft']);
        $event('hidden', ['visibility' => 'private']);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Home', false)
            ->where('spotlightEvents', fn ($events) => collect($events)->pluck('slug')->all() === ['headliner', 'happening-now', 'soon', 'later']));
    }

    public function test_home_categories_count_only_public_upcoming_events(): void
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $music = EventCategory::create(['name' => 'Music', 'slug' => 'music', 'sort' => 1, 'is_active' => true]);
        $event = fn (string $slug, array $attrs = []) => $org->events()->create([
            'title' => $slug, 'slug' => $slug, 'status' => 'published', 'type' => 'physical', 'visibility' => 'public',
            'start_at' => now()->addWeek(), 'currency' => 'UGX', 'event_category_id' => $music->id, ...$attrs,
        ]);
        $event('upcoming');
        $event('past', ['start_at' => now()->subWeek()]);
        $event('draft', ['status' => 'draft']);
        $event('private', ['visibility' => 'private']);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->where('categories.0.slug', 'music')
            ->where('categories.0.upcoming_count', 1));
    }

    public function test_about_page_shows_live_platform_figures(): void
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $org->events()->create(['title' => 'Gig', 'slug' => 'gig', 'status' => 'published', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addWeek(), 'currency' => 'UGX', 'city' => 'Kampala']);
        $org->events()->create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addWeek(), 'currency' => 'UGX', 'city' => 'Gulu']);

        $this->get('/about')->assertOk()->assertInertia(fn ($page) => $page
            ->component('About', false)
            ->where('stats.events', 1)
            ->where('stats.organizers', 1)
            ->where('stats.cities', 1));
    }
}
