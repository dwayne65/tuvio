<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Promoter;
use App\Support\EventPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Event::query()->public()->upcoming()
            ->with(['category', 'organization', 'ticketTypes']);

        if ($q = $request->string('q')->trim()->value()) {
            $query->where(fn ($sub) => $sub
                ->whereLike('title', "%{$q}%")
                ->orWhereLike('description', "%{$q}%")
                ->orWhereLike('venue_name', "%{$q}%")
                ->orWhereLike('city', "%{$q}%"));
        }

        if ($category = $request->string('category')->value()) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $category));
        }

        if ($type = $request->string('type')->value()) {
            $query->where('type', $type);
        }

        $sort = $request->string('sort')->value();
        match ($sort) {
            'soon' => $query->orderBy('start_at'),
            'newest' => $query->latest('published_at'),
            default => $query->orderByDesc('is_featured')->orderBy('start_at'),
        };

        $events = $query->paginate(12)->withQueryString();

        return Inertia::render('Events/Index', [
            'events' => [
                'data' => collect($events->items())->map(fn ($e) => EventPresenter::summary($e)),
                'meta' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'total' => $events->total(),
                ],
                'links' => $events->linkCollection()->toArray(),
            ],
            'categories' => EventCategory::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'slug', 'icon', 'color']),
            'filters' => [
                'q' => $request->string('q')->value(),
                'category' => $category,
                'type' => $type,
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        // Until an admin approves it, only the organizer's team and admins can preview an event.
        $user = $request->user();
        abort_unless(
            $event->isPublished() || ($user && ($user->isAdmin() || $user->belongsToOrganization($event->organization))),
            404,
        );

        $event->increment('views_count');
        $this->rememberReferral($request, $event);
        $event->load(['category', 'organization', 'scheduleItems' => fn ($q) => $q->orderBy('sort'), 'ticketTypes' => fn ($q) => $q->where('is_hidden', false)->orderBy('sort')]);

        $related = Event::query()->public()->upcoming()
            ->where('id', '!=', $event->id)
            ->where('event_category_id', $event->event_category_id)
            ->with(['category', 'organization', 'ticketTypes'])
            ->limit(3)->get();

        return Inertia::render('Events/Show', [
            'event' => [
                ...EventPresenter::summary($event),
                'description' => $event->description,
                'end_at' => $event->end_at?->toIso8601String(),
                'venue_address' => $event->venue_address,
                'online_url' => $event->type !== 'physical' ? $event->online_url : null,
                'timezone' => $event->timezone,
                'currency' => $event->currency,
                'refund_policy' => $event->refund_policy,
                // Booking fee rate added at checkout when the organizer passes the fee to buyers.
                'booking_fee_bps' => $event->passesFeeToBuyer() ? $event->commissionBps() : 0,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'schedule' => $event->scheduleItems->map(fn ($s) => [
                    'id' => $s->id, 'title' => $s->title, 'description' => $s->description,
                    'speaker' => $s->speaker, 'starts_at' => $s->starts_at?->toIso8601String(),
                ]),
                'ticket_types' => $event->ticketTypes->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'description' => $t->description,
                    'tier' => $t->resolvedTier(), 'perks' => $t->perks ?? [],
                    'price' => ['minor' => $t->price_minor, 'currency' => $t->currency, 'formatted' => $t->currency.' '.number_format($t->price_minor / 100)],
                    'is_free' => $t->isFree(), 'available' => $t->availableQuantity(),
                    'on_sale' => $t->isOnSale(), 'max_per_order' => $t->max_per_order,
                ]),
            ],
            'related' => $related->map(fn ($e) => EventPresenter::summary($e)),
            'reviews' => $this->reviewSummary($event),
        ]);
    }

    /** Credit a promoter's ?ref= link for later checkout (per organization, last click wins). */
    private function rememberReferral(Request $request, Event $event): void
    {
        $code = $request->string('ref')->trim()->upper()->value();
        if ($code === '') {
            return;
        }

        $promoter = Promoter::where('organization_id', $event->organization_id)->where('code', $code)->first();
        if ($promoter?->covers($event)) {
            $promoter->increment('clicks');
            $request->session()->put("referrals.{$event->organization_id}", $promoter->id);
        }
    }

    /** @return array{average: float|null, count: int, breakdown: array<int,int>, items: array<int,array<string,mixed>>} */
    private function reviewSummary(Event $event): array
    {
        $visible = $event->reviews()->where('is_visible', true);
        $counts = (clone $visible)->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');
        $count = (int) $counts->sum();

        return [
            'average' => $count > 0 ? round((clone $visible)->avg('rating'), 1) : null,
            'count' => $count,
            'breakdown' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [$r => (int) ($counts[$r] ?? 0)])->all(),
            'items' => (clone $visible)->latest()->limit(6)->get()->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'rating' => $r->rating, 'body' => $r->body,
                'at' => $r->created_at->toIso8601String(),
            ])->all(),
        ];
    }
}
