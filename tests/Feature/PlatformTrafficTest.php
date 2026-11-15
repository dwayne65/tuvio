<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTrafficTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private const LAPTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0 Safari/537.36';

    public function test_opening_a_page_records_a_view_from_its_source(): void
    {
        $this->get('/pricing', ['User-Agent' => self::PHONE, 'Referer' => 'https://www.google.com/search?q=tuvio'])->assertOk();

        $view = PageView::sole();
        $this->assertSame(['/pricing', true, 'google.com', 'mobile'], [$view->path, $view->is_entry, $view->referrer_host, $view->device]);
        $this->assertSame(now()->toDateString(), $view->visited_on->toDateString());
        $this->assertStringNotContainsString('127.0.0.1', $view->visitor);
    }

    public function test_navigating_inside_the_app_counts_as_a_view_but_not_a_new_visit(): void
    {
        $version = $this->get('/', ['User-Agent' => self::LAPTOP])->viewData('page')['version'];
        $this->get('/events', ['User-Agent' => self::LAPTOP, 'X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'Referer' => 'https://tuvio.org/'])->assertOk();

        [$first, $second] = PageView::orderBy('id')->get();
        $this->assertSame([true, false, null], [$first->is_entry, $second->is_entry, $second->referrer_host]);
        $this->assertSame($first->visitor, $second->visitor);
    }

    public function test_bots_failed_pages_partial_reloads_and_admins_are_not_counted(): void
    {
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
        $this->get('/', ['User-Agent' => ''])->assertOk();
        $this->get('/events/does-not-exist', ['User-Agent' => self::LAPTOP])->assertNotFound();
        $this->get('/sitemap.xml', ['User-Agent' => self::LAPTOP])->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/', ['User-Agent' => self::LAPTOP])->assertOk();

        $this->assertSame(0, PageView::count());
    }

    public function test_admin_dashboard_shows_views_visitors_pages_and_sources(): void
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $org->events()->create(['title' => 'Jazz Night', 'slug' => 'jazz-night', 'status' => 'published', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addWeek(), 'currency' => 'UGX']);

        PageView::factory()->entry('google.co.ug')->create(['path' => '/events/jazz-night', 'visitor' => 'a', 'device' => 'mobile']);
        PageView::factory()->create(['path' => '/events/jazz-night', 'visitor' => 'a']);
        PageView::factory()->entry()->create(['path' => '/', 'visitor' => 'b', 'device' => 'desktop']);
        PageView::factory()->entry('l.facebook.com')->create(['path' => '/', 'visitor' => 'c', 'device' => 'mobile', 'visited_on' => now()->subDays(3)->toDateString()]);
        PageView::factory()->create(['visited_on' => now()->subDays(45)->toDateString()]);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('traffic.today', ['views' => 3, 'visitors' => 2])
                ->where('traffic.week', ['views' => 4, 'visitors' => 3])
                ->where('traffic.month.views', 4)
                ->where('traffic.month.views_change', 300)
                ->where('traffic.all_time_views', 5)
                ->where('traffic.top_pages.0', ['path' => '/', 'label' => 'Home', 'views' => 2])
                ->where('traffic.top_pages.1', ['path' => '/events/jazz-night', 'label' => 'Jazz Night', 'views' => 2])
                ->where('traffic.sources', [['source' => 'Direct', 'visits' => 1], ['source' => 'Facebook', 'visits' => 1], ['source' => 'Google', 'visits' => 1]])
                ->where('traffic.devices', ['desktop' => 1, 'mobile' => 2]));
    }

    public function test_old_page_views_are_pruned(): void
    {
        $old = PageView::factory()->create(['visited_on' => now()->subDays(PageView::KEEP_DAYS + 1)->toDateString()]);
        $recent = PageView::factory()->create();

        $this->artisan('model:prune', ['--model' => [PageView::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
