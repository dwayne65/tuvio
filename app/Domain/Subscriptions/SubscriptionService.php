<?php

namespace App\Domain\Subscriptions;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages organizer subscriptions: starting a plan (with trial), changing plans,
 * cancelling and resuming. Paid activation without a trial is left "incomplete"
 * until a verified payment arrives (wired in the payments phase) — we never mark
 * a paid subscription active without settlement.
 */
class SubscriptionService
{
    /**
     * Subscribe an organization to a plan. If it already has a subscription this
     * performs an upgrade/downgrade in place.
     */
    public function subscribe(Organization $org, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($org, $plan) {
            $existing = $org->subscription()->latest()->first();

            $now = now();
            $trial = $plan->trial_days > 0;
            $free = $plan->price_minor === 0;

            // Determine status + period.
            $status = match (true) {
                $trial => 'trialing',
                $free => 'active',
                default => 'incomplete', // paid, no trial: awaits verified payment
            };

            $periodEnd = $this->periodEnd($now->copy(), $plan->interval);
            $trialEnds = $trial ? $now->copy()->addDays($plan->trial_days) : null;

            $attributes = [
                'subscription_plan_id' => $plan->id,
                'status' => $status,
                'trial_ends_at' => $trialEnds,
                'current_period_start' => $now,
                'current_period_end' => $trial ? $trialEnds : $periodEnd,
                'canceled_at' => null,
                'cancel_at_period_end' => false,
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
            ];

            $subscription = $existing
                ? tap($existing)->update($attributes)
                : $org->subscription()->create($attributes);

            // Generate an invoice for paid plans (payment collected in the payments phase).
            if (! $free) {
                SubscriptionInvoice::create([
                    'subscription_id' => $subscription->id,
                    'number' => 'INV-'.strtoupper(Str::random(10)),
                    'amount_minor' => $plan->price_minor,
                    'currency' => $plan->currency,
                    'status' => $trial ? 'open' : 'open',
                    'period_start' => $now,
                    'period_end' => $subscription->current_period_end,
                ]);
            }

            return $subscription->fresh('plan');
        });
    }

    /** Cancel at period end (keeps access until then) or immediately. */
    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        if ($atPeriodEnd) {
            $subscription->update(['cancel_at_period_end' => true, 'canceled_at' => now()]);
        } else {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_at_period_end' => false,
                'current_period_end' => now(),
            ]);
        }

        return $subscription->fresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        $subscription->update(['cancel_at_period_end' => false, 'canceled_at' => null]);

        return $subscription->fresh();
    }

    /** Whether the org may publish another event (active sub + within plan limits). */
    public function canPublishEvents(Organization $org): bool
    {
        $sub = $org->activeSubscription();
        if (! $sub) {
            return false;
        }

        $limit = $sub->plan->event_limit;
        if ($limit === null) {
            return true; // unlimited
        }

        $published = $org->events()->where('status', 'published')->count();

        return $published < $limit;
    }

    private function periodEnd(Carbon $from, string $interval): Carbon
    {
        return $interval === 'yearly' ? $from->addYear() : $from->addMonth();
    }
}
