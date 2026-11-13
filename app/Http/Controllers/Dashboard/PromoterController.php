<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Promoter;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PromoterController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $paid = fn ($q) => $q->where('status', 'paid');

        $promoters = $org->promoters()
            ->with('event:id,title,slug')
            ->withCount(['orders as orders_count' => $paid])
            ->withSum(['orders as revenue_minor' => $paid], 'total_minor')
            ->latest()->get()
            ->map(function (Promoter $p) {
                $revenue = (int) $p->revenue_minor;

                return [
                    'id' => $p->id, 'name' => $p->name, 'email' => $p->email, 'code' => $p->code,
                    'event' => $p->event?->title,
                    'link' => $p->event ? route('events.show', $p->event->slug).'?ref='.$p->code : null,
                    'commission_percent' => $p->commission_bps / 100,
                    'clicks' => $p->clicks, 'orders' => $p->orders_count,
                    'revenue_minor' => $revenue,
                    'earned_minor' => (int) round($revenue * $p->commission_bps / 10000),
                    'conversion' => $p->clicks > 0 ? round($p->orders_count / $p->clicks * 100, 1) : null,
                    'is_active' => $p->is_active,
                ];
            });

        $sample = $org->events()->where('status', 'published')->latest('start_at')->value('slug');

        return Inertia::render('Dashboard/Promoters', [
            'promoters' => $promoters,
            'events' => $org->events()->orderBy('title')->get(['id', 'title']),
            'sample_url' => $sample ? route('events.show', $sample) : null,
            'currency' => $org->default_currency,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = $this->organization($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('promoters')->where('organization_id', $org->id)->where(fn ($q) => $q->where('code', strtoupper($request->string('code')->value())))],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->where('organization_id', $org->id)],
        ]);

        $org->promoters()->create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'code' => strtoupper($data['code']),
            'commission_bps' => (int) round($data['commission_percent'] * 100),
            'event_id' => $data['event_id'] ?? null,
        ]);

        return back()->with('success', "{$data['name']} added as a promoter.");
    }

    public function toggle(Request $request, Promoter $promoter): RedirectResponse
    {
        $org = $this->organization($request);
        abort_unless($promoter->organization_id === $org->id, 404);

        $promoter->update(['is_active' => ! $promoter->is_active]);

        return back()->with('success', $promoter->is_active ? 'Promoter link activated.' : 'Promoter link paused.');
    }

    public function destroy(Request $request, Promoter $promoter): RedirectResponse
    {
        $org = $this->organization($request);
        abort_unless($promoter->organization_id === $org->id, 404);

        // Orders keep their sale but lose the attribution (FK is null-on-delete).
        $promoter->delete();

        return back()->with('success', 'Promoter removed.');
    }

    private function organization(Request $request): Organization
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageMarketing', $org);

        return $org;
    }
}
