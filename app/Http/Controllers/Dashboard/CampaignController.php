<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        return Inertia::render('Dashboard/Contributions', [
            'campaigns' => $org->campaigns()->latest()->get()->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'slug' => $c->slug, 'status' => $c->status,
                'goal_minor' => $c->goal_minor, 'raised_minor' => $c->raised_minor,
                'currency' => $c->currency, 'contributors_count' => $c->contributors_count,
                'progress' => $c->progressPercent(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('update', $org);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goal_major' => ['nullable', 'numeric', 'min:0'],
            'deadline_at' => ['nullable', 'date'],
        ]);

        $campaign = $org->campaigns()->create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'description' => $data['description'] ?? null,
            'goal_minor' => isset($data['goal_major']) ? (int) round($data['goal_major'] * 100) : null,
            'currency' => $org->default_currency,
            'status' => 'active',
            'deadline_at' => $data['deadline_at'] ?? null,
        ]);

        return back()->with('success', "Campaign “{$campaign->title}” is live.");
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $slug = $base;
        $i = 1;
        while (Campaign::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
