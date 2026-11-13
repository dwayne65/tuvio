<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\Ticket;
use Illuminate\Http\Request;
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
            'plans' => SubscriptionPlan::where('is_public', true)->where('is_active', true)->orderBy('sort')->get()
                ->map(fn (SubscriptionPlan $plan) => SubscriptionController::planProps($plan)),
            'capabilities' => SubscriptionPlan::CAPABILITIES,
            'currency' => config('ticketflow.default_currency'),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('About', [
            'stats' => [
                'events' => Event::query()->public()->count(),
                'tickets' => Ticket::whereIn('status', ['issued', 'used'])->count(),
                'organizers' => Organization::where('status', 'active')->count(),
                'cities' => Event::query()->public()->whereNotNull('city')->distinct()->count('city'),
            ],
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms');
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy');
    }

    public function contact(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Contact', [
            'prefill' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'order_reference' => $request->string('order')->upper()->limit(40, '')->value(),
            ],
        ]);
    }
}
