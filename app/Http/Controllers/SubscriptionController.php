<?php

namespace App\Http\Controllers;

use App\Domain\Subscriptions\SubscriptionService;
use App\Models\SubscriptionPlan;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }

        $this->authorize('view', $org);

        $current = $org->subscription()->with('plan')->latest()->first();

        return Inertia::render('Dashboard/Subscription', [
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort')->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug,
                    'description' => $p->description,
                    'price' => ['minor' => $p->price_minor, 'currency' => $p->currency, 'formatted' => number_format($p->price_minor / 100)],
                    'interval' => $p->interval, 'trial_days' => $p->trial_days,
                    'event_limit' => $p->event_limit, 'ticket_limit' => $p->ticket_limit,
                    'features' => $p->features ?? [],
                ]),
            'current' => $current ? [
                'id' => $current->id,
                'status' => $current->status,
                'plan_id' => $current->subscription_plan_id,
                'plan_name' => $current->plan->name,
                'current_period_end' => $current->current_period_end?->toIso8601String(),
                'trial_ends_at' => $current->trial_ends_at?->toIso8601String(),
                'cancel_at_period_end' => $current->cancel_at_period_end,
                'is_active' => $current->isActive(),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id']]);
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        $sub = $this->subscriptions->subscribe($org, $plan);

        $msg = match ($sub->status) {
            'trialing' => "Your {$plan->name} trial has started.",
            'active' => "You're now on the {$plan->name} plan.",
            default => "Subscription created — complete payment to activate {$plan->name}.",
        };

        return redirect()->route('subscription.index')->with('success', $msg);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $sub = $org->subscription()->latest()->firstOrFail();
        $this->subscriptions->cancel($sub, atPeriodEnd: true);

        return back()->with('success', 'Subscription will end at the close of your billing period.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $sub = $org->subscription()->latest()->firstOrFail();
        $this->subscriptions->resume($sub);

        return back()->with('success', 'Subscription resumed.');
    }
}
