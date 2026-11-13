<?php

namespace App\Http\Controllers;

use App\Domain\Subscriptions\SubscriptionService;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /** Onboarding form to "become an organizer". */
    public function create(): Response
    {
        return Inertia::render('Organizations/Create');
    }

    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'support_email' => ['nullable', 'email'],
        ]);

        $user = $request->user();

        $org = Organization::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'country' => $validated['country'] ?? null,
            'default_currency' => strtoupper($validated['default_currency'] ?? config('ticketflow.default_currency')),
            'support_email' => $validated['support_email'] ?? $user->email,
            // A platform admin approves every new organizer before the dashboard opens.
            'status' => $user->isAdmin() ? Organization::STATUS_ACTIVE : Organization::STATUS_PENDING,
        ]);

        // Owner is automatically an accepted member with the owner role.
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        // Every organizer starts on the free Starter plan; paid plans are an upgrade from there.
        if ($starter = SubscriptionPlan::free()) {
            $subscriptions->subscribeToFree($org, $starter);
        }

        session(['current_organization_id' => $org->id]);

        if ($org->isApproved()) {
            return redirect()->route('dashboard')->with('success', "Welcome, {$org->name}! You are on the Starter plan. Upgrade any time for more features.");
        }

        rescue(fn () => Notification::send(
            User::where('is_admin', true)->get(),
            new ApprovalRequestedNotification('organization', $org->name, $user->name),
        ));

        return redirect()->route('dashboard')->with('success', "Thanks! {$org->name} is submitted for review. We will email you once an admin approves it.");
    }

    /** Switch the active organization context (for users with several). */
    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->belongsToOrganization($organization), 403);

        session(['current_organization_id' => $organization->id]);

        return back();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
