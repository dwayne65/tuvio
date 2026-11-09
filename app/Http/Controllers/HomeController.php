<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\Ticket;
use App\Support\EventPresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $featured = Event::query()->public()->upcoming()
            ->where('is_featured', true)
            ->with(['category', 'organization', 'ticketTypes'])
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        $upcoming = Event::query()->public()->upcoming()
            ->with(['category', 'organization', 'ticketTypes'])
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        return Inertia::render('Home', [
            'categories' => EventCategory::where('is_active', true)->orderBy('sort')->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                    'icon' => $c->icon, 'color' => $c->color,
                ]),
            'featuredEvents' => $featured->map(fn ($e) => EventPresenter::summary($e)),
            'upcomingEvents' => $upcoming->map(fn ($e) => EventPresenter::summary($e)),
            'plans' => SubscriptionPlan::where('is_public', true)->orderBy('sort')->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug,
                    'price' => ['minor' => $p->price_minor, 'currency' => $p->currency, 'formatted' => number_format($p->price_minor / 100)],
                    'interval' => $p->interval, 'trial_days' => $p->trial_days,
                    'event_limit' => $p->event_limit, 'features' => $p->features ?? [],
                ]),
            'stats' => [
                'events' => Event::query()->public()->count(),
                'tickets' => Ticket::where('status', '!=', 'void')->count(),
                'organizers' => Organization::where('status', 'active')->count(),
            ],
        ]);
    }
}
