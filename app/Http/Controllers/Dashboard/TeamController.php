<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Subscriptions\SubscriptionService;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Organization team: invites, roles and removals. Owners and admins manage
 * the team, but only the owner may grant, change or remove the admin role.
 * The owner can never be changed or removed here.
 */
class TeamController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $user = $request->user();
        $members = $org->members()->with('user:id,name,email')
            ->orderByRaw('accepted_at is null')->oldest()->get();

        return Inertia::render('Dashboard/Team', [
            'members' => $members->map(fn (OrganizationMember $member) => [
                'id' => $member->id,
                'name' => $member->user?->name,
                'email' => $member->user?->email ?? $member->invite_email,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'status' => match (true) {
                    $member->isAccepted() => 'active',
                    $member->inviteExpired() => 'expired',
                    default => 'pending',
                },
                'invited_at' => $member->invited_at?->toIso8601String(),
                'is_you' => $member->user_id === $user->id && $member->isAccepted(),
            ]),
            'roles' => collect(OrganizationRole::assignable())->map(fn (OrganizationRole $role) => [
                'value' => $role->value, 'label' => $role->label(), 'description' => $role->description(),
            ]),
            'seats' => $this->subscriptions->teamSeats($org),
            'canManage' => $user->can('manageTeam', $org),
            'isOwner' => $user->roleIn($org) === OrganizationRole::Owner,
            'canInvite' => $this->subscriptions->canAddTeamMember($org),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = $this->organization($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)->only(OrganizationRole::assignable())],
        ]);
        $email = Str::lower($data['email']);
        $role = OrganizationRole::from($data['role']);
        $this->ensureCanAssign($request, $org, $role);

        $existingUser = User::whereRaw('lower(email) = ?', [$email])->first();
        $existing = $org->members()
            ->where(fn ($q) => $q->whereRaw('lower(invite_email) = ?', [$email])
                ->when($existingUser, fn ($q) => $q->orWhere('user_id', $existingUser->id)))
            ->first();

        if ($existing?->isAccepted()) {
            return back()->withErrors(['email' => 'This person is already on your team.']);
        }
        if ($existing) {
            return back()->withErrors(['email' => 'This person already has an invitation. Resend it from the list below.']);
        }
        if (! $this->subscriptions->canAddTeamMember($org)) {
            return back()->withErrors(['email' => 'Your plan has no free team seats. Upgrade your subscription or remove a member first.']);
        }

        $invite = $org->members()->create([
            'user_id' => $existingUser?->id,
            'role' => $role,
            'invite_email' => $email,
            'invite_token' => Str::random(64),
            'invited_at' => now(),
        ]);
        $delivered = $this->sendInvitation($request, $invite);
        $this->audit($request, 'team.invited', $invite, new: ['email' => $email, 'role' => $role->value]);

        return $delivered
            ? back()->with('success', "Invitation sent to {$email}.")
            : back()->with('error', "Invite saved, but the email to {$email} could not be sent. Check the mail settings, then resend.");
    }

    public function update(Request $request, OrganizationMember $member): RedirectResponse
    {
        $org = $this->organization($request);
        $this->ensureCanModify($request, $org, $member);

        $data = $request->validate([
            'role' => ['required', Rule::enum(OrganizationRole::class)->only(OrganizationRole::assignable())],
        ]);
        $role = OrganizationRole::from($data['role']);
        $this->ensureCanAssign($request, $org, $role);

        $old = $member->role;
        $member->update(['role' => $role]);
        $this->audit($request, 'team.role_changed', $member, old: ['role' => $old->value], new: ['role' => $role->value]);

        return back()->with('success', 'Role updated to '.$role->label().'.');
    }

    public function destroy(Request $request, OrganizationMember $member): RedirectResponse
    {
        $org = $this->organization($request);
        $this->ensureCanModify($request, $org, $member);

        $wasAccepted = $member->isAccepted();
        $member->delete();
        $this->audit($request, $wasAccepted ? 'team.removed' : 'team.invite_revoked', $member, old: [
            'email' => $member->user?->email ?? $member->invite_email, 'role' => $member->role->value,
        ]);

        return back()->with('success', $wasAccepted ? 'Member removed.' : 'Invitation cancelled.');
    }

    public function resend(Request $request, OrganizationMember $member): RedirectResponse
    {
        $org = $this->organization($request);
        $this->ensureCanModify($request, $org, $member);
        abort_if($member->isAccepted(), 404);

        $member->update(['invite_token' => Str::random(64), 'invited_at' => now()]);
        $delivered = $this->sendInvitation($request, $member);
        $this->audit($request, 'team.invite_resent', $member, new: ['email' => $member->invite_email]);

        return $delivered
            ? back()->with('success', "Invitation resent to {$member->invite_email}.")
            : back()->with('error', "The email to {$member->invite_email} could not be sent. Check the mail settings and try again.");
    }

    private function organization(Request $request): Organization
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageTeam', $org);

        return $org;
    }

    /** Only the owner may hand out the admin role. */
    private function ensureCanAssign(Request $request, Organization $org, OrganizationRole $role): void
    {
        abort_if($role === OrganizationRole::Admin && $request->user()->roleIn($org) !== OrganizationRole::Owner, 403);
    }

    /** The owner and your own membership are off limits; admins are the owner's to change. */
    private function ensureCanModify(Request $request, Organization $org, OrganizationMember $member): void
    {
        abort_unless($member->organization_id === $org->id, 404);
        abort_if($member->role === OrganizationRole::Owner, 403);
        abort_if($member->user_id === $request->user()->id && $member->isAccepted(), 403);
        abort_if($member->role === OrganizationRole::Admin && $request->user()->roleIn($org) !== OrganizationRole::Owner, 403);
    }

    /** Returns false when the mail server refused the message, so the inviter can be told. */
    private function sendInvitation(Request $request, OrganizationMember $invite): bool
    {
        try {
            Notification::route('mail', $invite->invite_email)
                ->notify(new TeamInvitationNotification($invite, $request->user()->name));
        } catch (TransportExceptionInterface $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(Request $request, string $action, Model $subject, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
