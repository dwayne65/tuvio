<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * The public plans. Every feature listed here is enforced in the app: limits by
     * SubscriptionService and OrderService, capabilities by the `plan:` route middleware.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Starter', 'slug' => 'starter', 'price_minor' => 0, 'commission_bps' => 450,
                'description' => 'Try TUVIO with one event, on us.',
                'interval' => 'monthly', 'trial_days' => 0,
                'event_limit' => 1, 'lifetime_event_limit' => true,
                'ticket_limit' => 200, 'team_member_limit' => 1, 'ussd_event_limit' => 0,
                'capabilities' => [],
                'features' => ['1 event to try TUVIO', 'Up to 200 tickets', 'QR tickets and gate check-in', 'Mobile Money and card payments'],
                'sort' => 1,
            ],
            [
                'name' => 'Professional', 'slug' => 'professional', 'price_minor' => 0, 'commission_bps' => 450,
                'description' => 'Everything to sell out your events, with no monthly fee. You only pay when you sell.',
                'interval' => 'monthly', 'trial_days' => 0,
                'event_limit' => 25, 'lifetime_event_limit' => false,
                'ticket_limit' => 5000, 'team_member_limit' => 5, 'ussd_event_limit' => 3,
                'capabilities' => ['advanced_analytics', 'promo_codes', 'promoters', 'attendee_export'],
                'features' => ['No monthly fee', 'Up to 25 live events', 'Up to 5,000 tickets per event', 'Advanced analytics', 'Promo codes and discounts', 'Promoter referral links', 'Attendee list export', 'USSD ticket sales for 3 events', '5 team seats'],
                'sort' => 2,
            ],
            [
                'name' => 'Enterprise', 'slug' => 'enterprise', 'price_minor' => 500000_00, 'commission_bps' => 370,
                'description' => 'The lowest fee on TUVIO, for venues and agencies selling at scale.',
                'interval' => 'monthly', 'trial_days' => 0,
                'event_limit' => null, 'lifetime_event_limit' => false,
                'ticket_limit' => null, 'team_member_limit' => null, 'ussd_event_limit' => null,
                'capabilities' => ['advanced_analytics', 'promo_codes', 'promoters', 'attendee_export', 'reports'],
                'features' => ['Lowest fee: 3.7% per paid ticket', 'Unlimited events and tickets', 'Everything in Professional', 'USSD ticket sales for unlimited events', 'Financial reports and exports', 'Unlimited team seats'],
                'sort' => 3,
            ],
        ];
    }

    public function run(): void
    {
        $currency = config('ticketflow.default_currency', 'UGX');

        foreach (self::definitions() as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, ['currency' => $currency, 'is_active' => true, 'is_public' => true]),
            );
        }
    }
}
