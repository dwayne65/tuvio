<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The invitee's side of a team invite. A link only works for the account
 * whose email it was sent to, so a forwarded link can't be used by someone else.
 * Guests without an account can register straight from the invite: the token
 * proves they own the inbox, so the new account starts verified.
 */
class TeamInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invite = $this->findInvite($token);
        $status = $this->status($request, $invite);

        if ($status === 'login') {
            // Bring them back here after signing in.
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return Inertia::render('Invitations/Show', [
            'invitation' => [
                'token' => $token,
                'email' => $invite->invite_email,
                'organization' => $invite->organization->name,
                'role' => $invite->role->label(),
                'role_description' => $invite->role->description(),
                'expires_at' => $invite->invited_at?->copy()->addDays(OrganizationMember::INVITE_TTL_DAYS)->toFormattedDayDateString(),
            ],
            'status' => $status,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = $this->findInvite($token);
        if ($this->status($request, $invite) !== 'ready') {
            return redirect()->route('invitations.show', $token);
        }

        return $this->join($request, $invite, $request->user());
    }

    public function register(Request $request, string $token): RedirectResponse
    {
        $invite = $this->findInvite($token);
        if ($this->status($request, $invite) !== 'register') {
            return redirect()->route('invitations.show', $token);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => Str::lower((string) $invite->invite_email),
            'password' => Hash::make($validated['password']),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return $this->join($request, $invite, $user);
    }

    private function join(Request $request, OrganizationMember $invite, User $user): RedirectResponse
    {
        DB::transaction(function () use ($invite, $user) {
            // A stale invite for someone who already joined another way is simply dropped.
            if ($invite->organization->members()->where('user_id', $user->id)->whereNotNull('accepted_at')->exists()) {
                $invite->delete();

                return;
            }

            $invite->update(['user_id' => $user->id, 'accepted_at' => now(), 'invite_token' => null]);
        });

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'team.invite_accepted',
            'auditable_type' => $invite->getMorphClass(),
            'auditable_id' => $invite->getKey(),
            'new_values' => ['role' => $invite->role->value],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $request->session()->forget('url.intended');
        session(['current_organization_id' => $invite->organization_id]);

        return redirect()->route($this->landingRoute($invite->role))
            ->with('success', "Welcome to {$invite->organization->name}! You're on the team as {$invite->role->label()}.");
    }

    /** Send each role straight to the part of the dashboard they work in. */
    private function landingRoute(OrganizationRole $role): string
    {
        return match ($role) {
            OrganizationRole::Manager => 'dashboard.events.index',
            OrganizationRole::Finance => 'dashboard.payouts.index',
            OrganizationRole::Marketing => 'dashboard.promoters.index',
            OrganizationRole::Staff => 'dashboard.check-in.index',
            default => 'dashboard',
        };
    }

    private function findInvite(string $token): OrganizationMember
    {
        return OrganizationMember::pending()->where('invite_token', $token)->with('organization')->firstOrFail();
    }

    /** @return 'ready'|'expired'|'wrong_account'|'register'|'login' */
    private function status(Request $request, OrganizationMember $invite): string
    {
        $email = Str::lower((string) $invite->invite_email);

        return match (true) {
            $invite->inviteExpired() => 'expired',
            $request->user() === null => User::whereRaw('lower(email) = ?', [$email])->exists() ? 'login' : 'register',
            Str::lower($request->user()->email) !== $email => 'wrong_account',
            default => 'ready',
        };
    }
}
