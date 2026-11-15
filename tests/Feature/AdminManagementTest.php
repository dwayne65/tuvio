<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Tests here drive requests directly; the browser's CSRF handling is covered by the app itself.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function organization(): Organization
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/organizations', ['name' => 'Acme Events '.uniqid()]);
        Organization::query()->update(['status' => Organization::STATUS_ACTIVE]);
        auth()->logout();

        return $owner->ownedOrganizations()->firstOrFail();
    }

    private function event(Organization $org): Event
    {
        return $org->events()->create([
            'title' => 'Gig '.uniqid(), 'slug' => 'gig-'.uniqid(), 'status' => 'published', 'type' => 'physical',
            'visibility' => 'public', 'start_at' => now()->modify('+1 month'), 'currency' => 'UGX',
        ]);
    }

    public function test_only_admins_can_open_the_management_pages(): void
    {
        $user = User::factory()->create();

        foreach (['/admin/events', '/admin/reports', '/admin/promoters', '/admin/users'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_remove_a_live_event_after_confirming_their_password(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->event($this->organization());

        $this->actingAs($admin)->delete("/admin/events/{$event->slug}", ['password' => 'password'])
            ->assertSessionHas('success');

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'admin.event_removed', 'auditable_id' => $event->id]);
        $this->actingAs($admin)->get('/admin/events')->assertOk();
    }

    public function test_event_is_kept_when_the_password_is_wrong(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->event($this->organization());

        $this->actingAs($admin)->delete("/admin/events/{$event->slug}", ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_admin_cannot_remove_themselves_another_admin_or_an_organizer(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $organizer = User::factory()->create();
        $this->actingAs($organizer)->post('/organizations', ['name' => 'Owner Co']);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}", ['password' => 'password'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete("/admin/users/{$otherAdmin->id}", ['password' => 'password'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete("/admin/users/{$organizer->id}", ['password' => 'password'])
            ->assertSessionHas('error');

        $this->assertNotNull(User::find($admin->id));
        $this->assertNotNull(User::find($otherAdmin->id));
        $this->assertNotNull(User::find($organizer->id));
    }

    public function test_admin_can_remove_an_ordinary_user_and_it_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/users/{$member->id}", ['password' => 'password'])
            ->assertSessionHas('success');

        $this->assertNull(User::find($member->id));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'admin.user_removed']);
    }

    public function test_users_page_only_offers_removal_where_it_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Ordinary Person']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users', false)
                ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Ordinary Person' && $row['can_remove'] === true)
                    && collect($rows)->contains(fn ($row) => $row['id'] === $admin->id && $row['can_remove'] === false)));
    }

    public function test_reports_render_and_export_csv(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/reports?days=7')
            ->assertInertia(fn ($page) => $page->component('Admin/Reports', false)->where('days', 7));

        $response = $this->actingAs($admin)->get('/admin/reports/export?report=organizers&days=30');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $header = str_getcsv(strtok($response->streamedContent(), "\n"));
        $this->assertSame(['Organizer', 'Currency', 'Paid orders', 'Revenue (minor units)'], $header);
    }

    public function test_reports_fall_back_to_safe_defaults_for_unknown_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/reports?days=9999')
            ->assertInertia(fn ($page) => $page->where('days', 30));

        $response = $this->actingAs($admin)->get('/admin/reports/export?report=bogus');
        $this->assertStringContainsString('tuvio-events-report', $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_pause_and_remove_any_promoter_link(): void
    {
        $admin = User::factory()->admin()->create();
        $org = $this->organization();
        $promoter = Promoter::create([
            'organization_id' => $org->id, 'name' => 'Sam', 'code' => 'SAM1', 'commission_bps' => 500, 'is_active' => true,
        ]);

        $this->actingAs($admin)->post("/admin/promoters/{$promoter->id}/toggle")->assertSessionHas('success');
        $this->assertFalse($promoter->fresh()->is_active);

        $this->actingAs($admin)->delete("/admin/promoters/{$promoter->id}", ['password' => 'password'])
            ->assertSessionHas('success');
        $this->assertNull(Promoter::find($promoter->id));
        $this->assertSame(2, AuditLog::where('user_id', $admin->id)->whereIn('action', ['admin.promoter_paused', 'admin.promoter_removed'])->count());
    }
}
