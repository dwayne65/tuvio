<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);
        $this->onPaidPlan($user->ownedOrganizations()->firstOrFail());

        return $user;
    }

    public function test_insight_pages_render_for_organizer(): void
    {
        $user = $this->organizer();

        foreach (['/dashboard/tickets', '/dashboard/attendees', '/dashboard/transactions', '/dashboard/analytics'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_attendee_csv_export(): void
    {
        $user = $this->organizer();

        $res = $this->actingAs($user)->get('/dashboard/attendees/export');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
    }

    public function test_admin_dashboard_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
