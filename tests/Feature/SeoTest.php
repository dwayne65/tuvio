<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = []): Event
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Kampala Live', 'slug' => 'kl-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create([
            'title' => 'Jazz Night', 'slug' => 'jazz-'.uniqid(), 'status' => 'published', 'type' => 'physical', 'visibility' => 'public',
            'start_at' => now()->addMonth(), 'currency' => 'UGX', 'venue_name' => 'Kololo Grounds', 'city' => 'Kampala',
            'description' => '<p>An evening of <b>live jazz</b>.</p>',
            ...$attributes,
        ]);
        $event->ticketTypes()->create(['name' => 'VIP', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX', 'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active']);

        return $event;
    }

    /** @return list<array<string, mixed>> */
    private function structuredData(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }

    public function test_home_page_describes_tuvio_to_search_engines(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('<meta name="description" content="Buy tickets for concerts', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta property="og:image" content="'.url('/brand/og-image.png').'">', false)
            ->getContent();

        $types = array_column($this->structuredData($html), '@type');
        $this->assertSame(['Organization', 'WebSite'], $types);
    }

    public function test_public_event_page_has_event_structured_data(): void
    {
        $event = $this->event();

        $html = $this->get("/events/{$event->slug}")->assertOk()
            ->assertSee('<title inertia>Jazz Night · Tickets · TUVIO</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('events.show', $event).'">', false)
            ->assertSee('index, follow, max-image-preview:large', false)
            ->getContent();

        [$schema] = $this->structuredData($html);
        $this->assertSame('Event', $schema['@type']);
        $this->assertSame('An evening of live jazz.', $schema['description']);
        $this->assertSame('Kampala', $schema['location']['address']['addressLocality']);
        $this->assertSame(['50000.00', 'UGX', 'https://schema.org/InStock'], [$schema['offers'][0]['price'], $schema['offers'][0]['priceCurrency'], $schema['offers'][0]['availability']]);
    }

    public function test_unlisted_event_is_kept_out_of_search_results_and_the_sitemap(): void
    {
        $event = $this->event(['visibility' => 'unlisted']);

        $this->get("/events/{$event->slug}")->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($event->slug);
    }

    public function test_event_title_cannot_break_out_of_the_structured_data_script(): void
    {
        $event = $this->event(['title' => '</script><script>alert(1)</script>']);

        $this->get("/events/{$event->slug}")->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_private_pages_are_not_indexed(): void
    {
        $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_sitemap_lists_public_pages_and_upcoming_events(): void
    {
        $event = $this->event();
        $this->event(['status' => 'draft', 'slug' => 'secret-draft']);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/events').'</loc>', false)
            ->assertSee('<loc>'.route('events.show', $event).'</loc>', false)
            ->assertDontSee('secret-draft');
    }
}
