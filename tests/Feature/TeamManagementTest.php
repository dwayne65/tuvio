<?php

namespace Tests\Feature;

use App\Domain\Subscriptions\SubscriptionService;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Organization} */
    private function organizationOnPlan(?int $teamMemberLimit = 5): array
    {
        $owner = User::factory()->create();
        $org = Organization::create(['owner_id' => $owner->id, 'name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        $org->members()->create(['user_id' => $owner->id, 'role' => OrganizationRole::Owner, 'accepted_at' => now()]);

        $plan = SubscriptionPlan::create([
            'name' => 'Plan', 'slug' => 'plan-'.uniqid(), 'price_minor' => 0, 'currency' => 'UGX', 'interval' => 'monthly',
            'trial_days' => 0, 'team_member_limit' => $teamMemberLimit, 'is_active' => true, 'is_public' => true, 'sort' => 1,
            'capabilities' => array_keys(SubscriptionPlan::CAPABILITIES),
        ]);
        app(SubscriptionService::class)->subscribeToFree($org, $plan);

        return [$owner, $org];
    }

    private function memberOf(Organization $org, OrganizationRole $role): User
    {
        $user = User::factory()->create();
        $org->members()->create(['user_id' => $user->id, 'role' => $role, 'accepted_at' => now()]);

        return $user;
    }

    private function pendingInvite(Organization $org, string $email, OrganizationRole $role = OrganizationRole::Manager): OrganizationMember
    {
        return $org->members()->create([
            'role' => $role, 'invite_email' => $email, 'invite_token' => 'token-'.uniqid(), 'invited_at' => now(),
        ]);
    }

    public function test_owner_invites_by_email_and_the_invitee_is_emailed(): void
    {
        Notification::fake();
        [$owner, $org] = $this->organizationOnPlan();

        $this->actingAs($owner)
            ->post('/dashboard/team', ['email' => 'New.Person@Example.com', 'role' => 'finance'])
            ->assertSessionHasNoErrors();

        $invite = $org->members()->pending()->sole();
        $this->assertSame('new.person@example.com', $invite->invite_email);
        $this->assertSame(OrganizationRole::Finance, $invite->role);
        $this->assertNull($invite->user_id);
        $this->assertTrue(AuditLog::where('action', 'team.invited')->exists());
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'new.person@example.com',
        );
    }

    public function test_pending_invite_grants_no_access_until_accepted(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $this->pendingInvite($org, 'invitee@example.com')->update(['user_id' => $invitee->id]);

        $this->actingAs($invitee)->get('/dashboard/events')->assertRedirect(route('organizations.create'));
        $this->assertFalse($invitee->belongsToOrganization($org));
    }

    public function test_invitee_accepts_and_joins_with_the_invited_role(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com', OrganizationRole::Marketing);
        $invitee = User::factory()->create(['email' => 'Invitee@example.com']);

        $this->actingAs($invitee)->get("/invitations/{$invite->invite_token}")
            ->assertInertia(fn ($page) => $page->component('Invitations/Show', false)->where('status', 'ready'));

        $this->actingAs($invitee)->post("/invitations/{$invite->invite_token}")
            ->assertRedirect(route('dashboard.promoters.index'))
            ->assertSessionHas('current_organization_id', $org->id);

        $invite->refresh();
        $this->assertSame($invitee->id, $invite->user_id);
        $this->assertNotNull($invite->accepted_at);
        $this->assertNull($invite->invite_token);
        $this->assertSame(OrganizationRole::Marketing, $invitee->fresh()->roleIn($org));
    }

    public function test_invite_link_cannot_be_used_by_a_different_account(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com');
        $someoneElse = User::factory()->create();

        $this->actingAs($someoneElse)->post("/invitations/{$invite->invite_token}")
            ->assertRedirect(route('invitations.show', $invite->invite_token));

        $this->assertNull($invite->fresh()->accepted_at);
        $this->assertFalse($someoneElse->belongsToOrganization($org));
    }

    public function test_expired_invite_cannot_be_accepted(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com');
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $this->travel(OrganizationMember::INVITE_TTL_DAYS + 1)->days();

        $this->actingAs($invitee)->post("/invitations/{$invite->invite_token}");

        $this->assertNull($invite->fresh()->accepted_at);
    }

    public function test_guest_with_an_existing_account_is_asked_to_sign_in_and_returned_to_the_invite(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com');
        User::factory()->create(['email' => 'invitee@example.com']);

        $this->get("/invitations/{$invite->invite_token}")
            ->assertInertia(fn ($page) => $page->component('Invitations/Show', false)->where('status', 'login'))
            ->assertSessionHas('url.intended', route('invitations.show', $invite->invite_token));
    }

    public function test_guest_registers_from_the_invite_and_lands_in_their_role(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'Invitee@Example.com', OrganizationRole::Finance);

        $this->get("/invitations/{$invite->invite_token}")
            ->assertInertia(fn ($page) => $page->component('Invitations/Show', false)->where('status', 'register'));

        $this->post("/invitations/{$invite->invite_token}/register", [
            'name' => 'New Person',
            'email' => 'attacker@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('dashboard.payouts.index'))
            ->assertSessionHas('current_organization_id', $org->id);

        $user = User::where('email', 'invitee@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(OrganizationRole::Finance, $user->roleIn($org));
        $this->assertNull($invite->fresh()->invite_token);
        $this->assertFalse(User::where('email', 'attacker@example.com')->exists());
    }

    public function test_cannot_register_from_an_invite_whose_email_already_has_an_account(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com');
        User::factory()->create(['email' => 'invitee@example.com']);

        $this->post("/invitations/{$invite->invite_token}/register", [
            'name' => 'Hijacker', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('invitations.show', $invite->invite_token));

        $this->assertGuest();
        $this->assertNull($invite->fresh()->accepted_at);
    }

    public function test_cannot_register_from_an_expired_or_used_invite(): void
    {
        [, $org] = $this->organizationOnPlan();
        $expired = $this->pendingInvite($org, 'late@example.com');
        $this->travel(OrganizationMember::INVITE_TTL_DAYS + 1)->days();
        $payload = ['name' => 'Late', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'];

        $this->post("/invitations/{$expired->invite_token}/register", $payload)
            ->assertRedirect(route('invitations.show', $expired->invite_token));
        $this->post('/invitations/no-such-token/register', $payload)->assertNotFound();

        $this->assertGuest();
        $this->assertFalse(User::where('email', 'late@example.com')->exists());
    }

    public function test_inviter_is_told_when_the_invitation_email_cannot_be_delivered(): void
    {
        [$owner] = $this->organizationOnPlan();
        Notification::shouldReceive('send')
            ->andThrow(new TransportException('Connection refused'));

        $this->actingAs($owner)
            ->post('/dashboard/team', ['email' => 'someone@example.com', 'role' => 'staff'])
            ->assertSessionHas('error');

        $this->assertTrue(OrganizationMember::where('invite_email', 'someone@example.com')->exists());
    }

    public function test_invitation_email_offers_registration_to_new_people(): void
    {
        [, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com', OrganizationRole::Staff);

        $html = (string) (new TeamInvitationNotification($invite, 'Ada Owner'))
            ->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('Create account &amp; join', $html);
        $this->assertStringContainsString('Ada Owner', $html);
        $this->assertStringContainsString(route('invitations.show', $invite->invite_token), $html);
    }

    public function test_invite_is_refused_when_the_plan_has_no_free_seats(): void
    {
        Notification::fake();
        [$owner] = $this->organizationOnPlan(teamMemberLimit: 1);

        $this->actingAs($owner)
            ->post('/dashboard/team', ['email' => 'new@example.com', 'role' => 'staff'])
            ->assertSessionHasErrors(['email' => 'Your plan has no free team seats. Upgrade your subscription or remove a member first.']);

        Notification::assertNothingSent();
    }

    public function test_pending_invites_take_a_seat_until_they_expire(): void
    {
        [, $org] = $this->organizationOnPlan(teamMemberLimit: 2);
        $this->pendingInvite($org, 'first@example.com');
        $seats = app(SubscriptionService::class);

        $this->assertFalse($seats->canAddTeamMember($org));

        $this->travel(OrganizationMember::INVITE_TTL_DAYS + 1)->days();

        $this->assertTrue($seats->canAddTeamMember($org));
    }

    public function test_inviting_an_existing_member_is_rejected(): void
    {
        [$owner, $org] = $this->organizationOnPlan();
        $member = $this->memberOf($org, OrganizationRole::Staff);

        $this->actingAs($owner)
            ->post('/dashboard/team', ['email' => $member->email, 'role' => 'manager'])
            ->assertSessionHasErrors(['email' => 'This person is already on your team.']);
    }

    public function test_inviting_someone_twice_points_to_resend(): void
    {
        [$owner, $org] = $this->organizationOnPlan();
        $this->pendingInvite($org, 'invitee@example.com');

        $this->actingAs($owner)
            ->post('/dashboard/team', ['email' => 'INVITEE@example.com', 'role' => 'manager'])
            ->assertSessionHasErrors(['email' => 'This person already has an invitation. Resend it from the list below.']);
    }

    public function test_ownership_cannot_be_handed_out_by_invite(): void
    {
        [$owner] = $this->organizationOnPlan();

        $this->actingAs($owner)
            ->post('/dashboard/team', ['email' => 'new@example.com', 'role' => 'owner'])
            ->assertSessionHasErrors('role');
    }

    public function test_only_the_owner_can_grant_the_admin_role(): void
    {
        [, $org] = $this->organizationOnPlan();
        $admin = $this->memberOf($org, OrganizationRole::Admin);
        $manager = $this->memberOf($org, OrganizationRole::Manager);
        $managerMembership = $org->members()->where('user_id', $manager->id)->sole();

        $this->actingAs($admin)->post('/dashboard/team', ['email' => 'new@example.com', 'role' => 'admin'])->assertForbidden();
        $this->actingAs($admin)->patch("/dashboard/team/{$managerMembership->id}", ['role' => 'admin'])->assertForbidden();

        $this->assertSame(OrganizationRole::Manager, $managerMembership->fresh()->role);
    }

    public function test_admin_cannot_change_or_remove_the_owner(): void
    {
        [, $org] = $this->organizationOnPlan();
        $admin = $this->memberOf($org, OrganizationRole::Admin);
        $ownerMembership = $org->members()->where('role', OrganizationRole::Owner)->sole();

        $this->actingAs($admin)->patch("/dashboard/team/{$ownerMembership->id}", ['role' => 'staff'])->assertForbidden();
        $this->actingAs($admin)->delete("/dashboard/team/{$ownerMembership->id}")->assertForbidden();

        $this->assertSame(OrganizationRole::Owner, $ownerMembership->fresh()->role);
    }

    public function test_members_without_team_access_cannot_invite(): void
    {
        [, $org] = $this->organizationOnPlan();
        $manager = $this->memberOf($org, OrganizationRole::Manager);

        $this->actingAs($manager)->post('/dashboard/team', ['email' => 'new@example.com', 'role' => 'staff'])->assertForbidden();
    }

    public function test_cannot_change_a_member_of_another_organization(): void
    {
        [$owner] = $this->organizationOnPlan();
        [, $otherOrg] = $this->organizationOnPlan();
        $outsider = $this->memberOf($otherOrg, OrganizationRole::Staff);
        $outsiderMembership = $otherOrg->members()->where('user_id', $outsider->id)->sole();

        $this->actingAs($owner)->delete("/dashboard/team/{$outsiderMembership->id}")->assertNotFound();

        $this->assertModelExists($outsiderMembership);
    }

    public function test_owner_changes_a_role_and_removes_a_member(): void
    {
        [$owner, $org] = $this->organizationOnPlan();
        $member = $this->memberOf($org, OrganizationRole::Staff);
        $membership = $org->members()->where('user_id', $member->id)->sole();

        $this->actingAs($owner)->patch("/dashboard/team/{$membership->id}", ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame(OrganizationRole::Admin, $membership->fresh()->role);

        $this->actingAs($owner)->delete("/dashboard/team/{$membership->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($membership);
        $this->assertSame(['team.role_changed', 'team.removed'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_resending_an_invite_issues_a_new_link_and_restarts_the_expiry(): void
    {
        Notification::fake();
        [$owner, $org] = $this->organizationOnPlan();
        $invite = $this->pendingInvite($org, 'invitee@example.com');
        $oldToken = $invite->invite_token;

        $this->travel(OrganizationMember::INVITE_TTL_DAYS + 1)->days();

        $this->actingAs($owner)->post("/dashboard/team/{$invite->id}/resend")->assertSessionHasNoErrors();

        $invite->refresh();
        $this->assertNotSame($oldToken, $invite->invite_token);
        $this->assertFalse($invite->inviteExpired());
        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 1);
    }

    public function test_managers_can_create_events_but_finance_cannot(): void
    {
        [, $org] = $this->organizationOnPlan();
        $manager = $this->memberOf($org, OrganizationRole::Manager);
        $finance = $this->memberOf($org, OrganizationRole::Finance);

        $this->actingAs($manager)->get('/dashboard/events/create')->assertOk();
        $this->actingAs($finance)->get('/dashboard/events/create')->assertForbidden();
    }

    public function test_marketing_can_manage_promoters_but_staff_cannot(): void
    {
        [, $org] = $this->organizationOnPlan();
        $promoter = $org->promoters()->create(['name' => 'Ada', 'code' => 'ADA', 'commission_bps' => 1000, 'is_active' => true]);

        $this->actingAs($this->memberOf($org, OrganizationRole::Staff))->post("/dashboard/promoters/{$promoter->id}/toggle")->assertForbidden();
        $this->actingAs($this->memberOf($org, OrganizationRole::Marketing))->post("/dashboard/promoters/{$promoter->id}/toggle")->assertSessionHasNoErrors();

        $this->assertFalse($promoter->fresh()->is_active);
    }
}
