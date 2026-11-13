<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\OrganizationReviewedNotification;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizerApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrganizer(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);

        return [$user, Organization::firstOrFail()];
    }

    public function test_new_organizer_waits_for_approval_and_admins_are_notified(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        [$user, $org] = $this->pendingOrganizer();

        $this->assertSame(Organization::STATUS_PENDING, $org->status);
        Notification::assertSentTo($admin, ApprovalRequestedNotification::class);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        foreach (['/dashboard/events', '/dashboard/events/create', '/dashboard/team', '/dashboard/subscription'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect('/dashboard');
        }
        $this->actingAs($user)->post('/dashboard/events', ['title' => 'Spam'])->assertRedirect('/dashboard');
        $this->assertSame(0, $org->events()->count());
    }

    public function test_admin_approval_opens_the_dashboard(): void
    {
        Notification::fake();
        [$user, $org] = $this->pendingOrganizer();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/approvals')->assertOk();
        $this->actingAs($admin)->post("/admin/organizations/{$org->id}/approve")->assertSessionHas('success');

        $org->refresh();
        $this->assertTrue($org->isApproved());
        $this->assertSame($admin->id, $org->approved_by);
        Notification::assertSentTo($user, OrganizationReviewedNotification::class, fn ($n) => $n->approved);

        $this->actingAs($user)->get('/dashboard/events')->assertOk();
    }

    public function test_admin_rejection_needs_a_reason_and_keeps_the_dashboard_closed(): void
    {
        Notification::fake();
        [$user, $org] = $this->pendingOrganizer();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/admin/organizations/{$org->id}/reject", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/organizations/{$org->id}/reject", ['reason' => 'Could not verify the business.']);

        $this->assertSame(Organization::STATUS_REJECTED, $org->fresh()->status);
        Notification::assertSentTo($user, OrganizationReviewedNotification::class, fn ($n) => ! $n->approved);
        $this->actingAs($user)->get('/dashboard/events')->assertRedirect('/dashboard');
    }

    public function test_only_admins_can_approve_organizers(): void
    {
        [$user, $org] = $this->pendingOrganizer();

        $this->actingAs($user)->post("/admin/organizations/{$org->id}/approve")->assertForbidden();
        $this->assertSame(Organization::STATUS_PENDING, $org->fresh()->status);
    }

    public function test_a_new_organizer_starts_on_the_starter_plan(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        [, $org] = $this->pendingOrganizer();

        $this->assertSame('starter', $org->activeSubscription()?->plan->slug);
    }

    public function test_admins_are_sent_to_the_admin_workspace(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    }
}
