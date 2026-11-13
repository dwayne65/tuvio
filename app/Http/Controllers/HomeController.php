<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\Ticket;
use App\Support\EventPresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        // Every live event: published, public and not yet over (featured first). Capped to keep the page light.
        $spotlight = Event::query()->public()
            ->where(fn ($q) => $q->where('start_at', '>=', now())->orWhere('end_at', '>=', now()))
            ->with(['category', 'organization', 'ticketTypes'])
            ->orderByDesc('is_featured')
            ->orderBy('start_at')
            ->limit(30)
            ->get();

        return Inertia::render('Home', [
            'spotlightEvents' => $spotlight->map(fn ($e) => EventPresenter::summary($e)),
            'categories' => EventCategory::where('is_active', true)->orderBy('sort')
                ->withCount(['events as upcoming_count' => fn ($q) => $q->public()->upcoming()])
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                    'icon' => $c->icon, 'color' => $c->color, 'upcoming_count' => $c->upcoming_count,
                ]),
            'stats' => [
                'events' => Event::query()->public()->count(),
                'tickets' => Ticket::where('status', '!=', 'void')->count(),
                'organizers' => Organization::where('status', 'active')->count(),
            ],
        ]);
    }
}
