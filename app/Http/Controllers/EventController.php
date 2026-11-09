<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
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

    public function show(Event $event): Response
    {
        abort_unless($event->status === 'published' || $event->visibility !== 'private', 404);

        $event->increment('views_count');
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
        ]);
    }
}
