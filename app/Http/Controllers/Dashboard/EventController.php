<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Events\EventService;
use App\Domain\Subscriptions\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\TicketType;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $events = $org->events()->withCount(['tickets', 'ticketTypes'])
            ->latest()->paginate(10)->through(fn ($e) => [
                'id' => $e->id, 'title' => $e->title, 'slug' => $e->slug,
                'status' => $e->status, 'start_at' => $e->start_at?->toIso8601String(),
                'tickets_count' => $e->tickets_count, 'ticket_types_count' => $e->ticket_types_count,
                'is_featured' => $e->is_featured,
            ]);

        return Inertia::render('Dashboard/Events/Index', [
            'events' => $events,
            'canPublish' => app(SubscriptionService::class)->canPublishEvents($org),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('create', [Event::class, $org]);

        return Inertia::render('Dashboard/Events/Create', [
            'categories' => EventCategory::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'slug']),
            'currency' => $org->default_currency,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('create', [Event::class, $org]);

        $data = $this->validateEvent($request);
        $ticketTypes = $this->validateTicketTypes($request);

        $event = DB::transaction(function () use ($org, $data, $ticketTypes) {
            $cover = $data['cover'] ?? null;
            $event = $this->events->createDraft($org, collect($data)->except('cover')->all());

            foreach ($ticketTypes as $i => $t) {
                $event->ticketTypes()->create([...$t, 'currency' => $event->currency, 'sort' => $i]);
            }

            if ($cover) {
                $event->update(['cover_image_path' => $cover->store('event-covers', 'public')]);
            }

            return $event;
        });

        return redirect()->route('dashboard.events.edit', $event)
            ->with('success', 'Event created as a draft. Review and publish when ready.');
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);
        $event->load('ticketTypes');

        return Inertia::render('Dashboard/Events/Edit', [
            'event' => [
                'id' => $event->id, 'title' => $event->title, 'slug' => $event->slug,
                'description' => $event->description, 'status' => $event->status,
                'type' => $event->type, 'visibility' => $event->visibility,
                'event_category_id' => $event->event_category_id,
                'start_at' => $event->start_at?->format('Y-m-d\TH:i'),
                'end_at' => $event->end_at?->format('Y-m-d\TH:i'),
                'venue_name' => $event->venue_name, 'venue_address' => $event->venue_address,
                'city' => $event->city, 'online_url' => $event->online_url,
                'currency' => $event->currency, 'refund_policy' => $event->refund_policy,
                'cover_url' => $event->cover_url,
                'public_url' => route('events.show', $event->slug),
                'ticket_types' => $event->ticketTypes->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'kind' => $t->kind, 'tier' => $t->resolvedTier(),
                    'description' => $t->description, 'perks' => $t->perks ?? [],
                    'price_minor' => $t->price_minor, 'quantity_total' => $t->quantity_total,
                    'quantity_sold' => $t->quantity_sold, 'max_per_order' => $t->max_per_order,
                    'sales_start_at' => $t->sales_start_at?->format('Y-m-d\TH:i'),
                    'sales_end_at' => $t->sales_end_at?->format('Y-m-d\TH:i'),
                    'status' => $t->status,
                ]),
            ],
            'categories' => EventCategory::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $data = $this->validateEvent($request);

        $this->events->update($event, collect($data)->except(['ticket_types', 'cover'])->all());

        return back()->with('success', 'Event updated.');
    }

    public function updateCover(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $request->validate(['cover' => ['required', 'image', 'max:4096']]);

        if ($event->cover_image_path && ! str_starts_with($event->cover_image_path, 'http')) {
            Storage::disk('public')->delete($event->cover_image_path);
        }

        $path = $request->file('cover')->store('event-covers', 'public');
        $event->update(['cover_image_path' => $path]);

        return back()->with('success', 'Cover image updated.');
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('publish', $event);

        try {
            $this->events->publish($event);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Event published! It is now live and discoverable.');
    }

    public function unpublish(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('publish', $event);
        $this->events->unpublish($event);

        return back()->with('success', 'Event unpublished.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('publish', $event);
        $this->events->cancel($event);

        return back()->with('success', 'Event cancelled.');
    }

    public function duplicate(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $copy = $this->events->duplicate($event);

        return redirect()->route('dashboard.events.edit', $copy)->with('success', 'Event duplicated.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        abort_if($event->tickets()->exists(), 422, 'Cannot delete an event that has issued tickets.');
        $event->delete();

        return redirect()->route('dashboard.events.index')->with('success', 'Event deleted.');
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_category_id' => ['nullable', 'exists:event_categories,id'],
            'type' => ['required', Rule::in(['physical', 'online', 'hybrid'])],
            'visibility' => ['required', Rule::in(['public', 'unlisted', 'private'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'online_url' => ['nullable', 'url'],
            'currency' => ['nullable', 'string', 'size:3'],
            'refund_policy' => ['nullable', 'string'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function validateTicketTypes(Request $request): array
    {
        $validated = $request->validate([
            'ticket_types' => ['required', 'array', 'min:1'],
            'ticket_types.*.name' => ['required', 'string', 'max:120'],
            'ticket_types.*.kind' => ['required', Rule::in(['free', 'paid'])],
            'ticket_types.*.price_minor' => ['required', 'integer', 'min:0'],
            'ticket_types.*.quantity_total' => ['nullable', 'integer', 'min:1'],
            'ticket_types.*.max_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'ticket_types.*.tier' => ['nullable', Rule::in(TicketType::TIERS)],
            'ticket_types.*.description' => ['nullable', 'string', 'max:500'],
            'ticket_types.*.perks' => ['nullable', 'array', 'max:8'],
            'ticket_types.*.perks.*' => ['nullable', 'string', 'max:60'],
            'ticket_types.*.sales_start_at' => ['nullable', 'date'],
            'ticket_types.*.sales_end_at' => ['nullable', 'date', 'after:ticket_types.*.sales_start_at'],
            'ticket_types.*.status' => ['nullable', Rule::in(['active', 'paused', 'hidden'])],
        ]);

        return array_map(fn (array $type) => [
            ...$type,
            'price_minor' => $type['kind'] === 'free' ? 0 : $type['price_minor'],
            'perks' => array_values(array_filter(array_map(fn ($perk) => trim((string) $perk), $type['perks'] ?? []))),
            'status' => $type['status'] ?? 'active',
        ], $validated['ticket_types']);
    }
}
