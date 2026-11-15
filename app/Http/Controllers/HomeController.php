<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\Ticket;
use App\Support\EventPresenter;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Seconds the homepage data is cached. Event, ticket type and category changes clear it
     * straight away (see forgetCache), so this mainly bounds how stale the ticket count gets.
     */
    private const SPOTLIGHT_TTL = 600;

    private const SUMMARY_TTL = 600;

    /** @var list<string> */
    private const CACHE_KEYS = ['home:spotlight', 'home:categories', 'home:stats'];

    /** Drops the cached homepage data so the next visit shows the latest events. */
    public static function forgetCache(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    public function index(): Response
    {
        // Every live event: published, public and not yet over (featured first). Capped to keep the page light.
        $spotlight = Cache::remember('home:spotlight', self::SPOTLIGHT_TTL, fn () => Event::query()->public()
            ->where(fn ($q) => $q->where('start_at', '>=', now())->orWhere('end_at', '>=', now()))
            ->with(['category', 'organization', 'ticketTypes'])
            ->orderByDesc('is_featured')
            ->orderBy('start_at')
            ->limit(30)
            ->get()
            ->map(fn ($e) => EventPresenter::summary($e))
            ->all());

        return Inertia::render('Home', [
            'spotlightEvents' => $spotlight,
            'categories' => Cache::remember('home:categories', self::SUMMARY_TTL, fn () => EventCategory::where('is_active', true)->orderBy('sort')
                ->withCount(['events as upcoming_count' => fn ($q) => $q->public()->upcoming()])
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                    'icon' => $c->icon, 'color' => $c->color, 'upcoming_count' => $c->upcoming_count,
                ])
                ->all()),
            'stats' => Cache::remember('home:stats', self::SUMMARY_TTL, fn () => [
                'events' => Event::query()->public()->count(),
                'tickets' => Ticket::where('status', '!=', 'void')->count(),
                'organizers' => Organization::where('status', 'active')->count(),
            ]),
        ]);
    }
}
