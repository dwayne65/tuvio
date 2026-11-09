<?php

namespace App\Http\Controllers;

use App\Models\EventCategory;
use App\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function categories(): Response
    {
        return Inertia::render('Categories', [
            'categories' => EventCategory::where('is_active', true)->orderBy('sort')
                ->withCount(['events' => fn ($q) => $q->public()->upcoming()])
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                    'icon' => $c->icon, 'color' => $c->color,
                    'description' => $c->description, 'events_count' => $c->events_count,
                ]),
        ]);
    }

    public function pricing(): Response
    {
        return Inertia::render('Pricing', [
            'plans' => SubscriptionPlan::where('is_public', true)->orderBy('sort')->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug,
                    'description' => $p->description,
                    'price' => ['minor' => $p->price_minor, 'currency' => $p->currency, 'formatted' => number_format($p->price_minor / 100)],
                    'interval' => $p->interval, 'trial_days' => $p->trial_days,
                    'event_limit' => $p->event_limit, 'ticket_limit' => $p->ticket_limit,
                    'features' => $p->features ?? [],
                ]),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('About');
    }

    public function contact(): Response
    {
        return Inertia::render('Contact');
    }
}
