<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $currency = config('ticketflow.default_currency', 'UGX');

        $plans = [
            [
                'name' => 'Starter', 'slug' => 'starter', 'price_minor' => 0,
                'interval' => 'monthly', 'trial_days' => 0,
                'event_limit' => 3, 'ticket_limit' => 200, 'team_member_limit' => 1,
                'features' => ['Up to 3 published events', 'Basic ticket management', 'Standard analytics', 'Email support'],
                'sort' => 1,
            ],
            [
                'name' => 'Professional', 'slug' => 'professional', 'price_minor' => 150000_00,
                'interval' => 'monthly', 'trial_days' => 14,
                'event_limit' => 25, 'ticket_limit' => 5000, 'team_member_limit' => 5,
                'features' => ['Up to 25 events', 'Advanced analytics', 'Multiple ticket categories', 'Promo codes & marketing', 'Attendee management', 'Priority support'],
                'sort' => 2,
            ],
            [
                'name' => 'Enterprise', 'slug' => 'enterprise', 'price_minor' => 500000_00,
                'interval' => 'monthly', 'trial_days' => 14,
                'event_limit' => null, 'ticket_limit' => null, 'team_member_limit' => null,
                'features' => ['Unlimited events', 'Advanced reporting', 'Team management', 'Custom branding', 'API access', 'Dedicated support'],
                'sort' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, ['currency' => $currency, 'is_active' => true, 'is_public' => true]),
            );
        }
    }
}
