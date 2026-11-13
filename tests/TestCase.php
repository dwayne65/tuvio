<?php

namespace Tests;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Put an organization on a paid-up plan, as a verified plan payment would. Defaults to
     * every capability so tests of paid dashboard features aren't blocked by plan gating.
     *
     * @param  list<string>|null  $capabilities
     */
    protected function onPaidPlan(Organization $org, ?array $capabilities = null, array $planAttributes = []): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Paid', 'slug' => 'paid-'.uniqid(), 'price_minor' => 15000000, 'currency' => 'UGX',
            'interval' => 'monthly', 'trial_days' => 0, 'is_active' => true, 'is_public' => true, 'sort' => 2,
            'capabilities' => $capabilities ?? array_keys(SubscriptionPlan::CAPABILITIES),
            ...$planAttributes,
        ]);

        $org->subscription()->create([
            'subscription_plan_id' => $plan->id, 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
            'price_minor' => $plan->price_minor, 'currency' => $plan->currency, 'interval' => $plan->interval,
        ]);

        return $plan;
    }
}
