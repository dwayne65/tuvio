<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\EventReview;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Organizer view of buyer reviews, with the ability to hide abusive ones. */
class ReviewController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $base = EventReview::whereHas('event', fn ($q) => $q->where('organization_id', $org->id));

        $reviews = (clone $base)->with('event:id,title,slug')->latest()->paginate(15)
            ->through(fn (EventReview $r) => [
                'id' => $r->id, 'name' => $r->name, 'rating' => $r->rating, 'body' => $r->body,
                'event' => $r->event->title, 'event_slug' => $r->event->slug,
                'is_visible' => $r->is_visible, 'at' => $r->created_at->toIso8601String(),
            ]);

        return Inertia::render('Dashboard/Reviews', [
            'reviews' => $reviews,
            'summary' => [
                'count' => (clone $base)->count(),
                'average' => ($avg = (clone $base)->avg('rating')) ? round($avg, 1) : null,
                'hidden' => (clone $base)->where('is_visible', false)->count(),
            ],
        ]);
    }

    public function toggle(Request $request, EventReview $review): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('update', $org);
        abort_unless($review->event->organization_id === $org->id, 404);

        $review->update(['is_visible' => ! $review->is_visible]);

        return back()->with('success', $review->is_visible ? 'Review is visible again.' : 'Review hidden from the event page.');
    }
}
