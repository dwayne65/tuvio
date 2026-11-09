<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
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
            'status' => 'active',
        ]);

        // Owner is automatically an accepted member with the owner role.
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        session(['current_organization_id' => $org->id]);

        return redirect()->route('dashboard')->with('success', "Welcome, {$org->name}! Choose a plan to start publishing events.");
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
