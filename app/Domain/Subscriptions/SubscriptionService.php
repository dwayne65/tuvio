<?php

namespace App\Domain\Subscriptions;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Manages organizer subscriptions. The free plan applies without payment; a paid plan
 * is only ever switched on by activate(), which runs after the payment provider has
 * verified the invoice's payment server-side. Until then the organization keeps the
 * plan it already had, so choosing a paid plan never unlocks anything by itself.
 */
class SubscriptionService
{
    /**
     * Switch to the free plan. A paid plan that is still running is kept until the end
     * of its period instead of being cut short.
     */
    public function subscribeToFree(Organization $org, SubscriptionPlan $plan): Subscription
    {
        if (! $plan->isFree()) {
            throw new RuntimeException('This plan requires payment.');
        }

        return DB::transaction(function () use ($org, $plan) {
            $active = $org->activeSubscription();

            if ($active && ! $active->plan->isFree()) {
                return $this->cancel($active, atPeriodEnd: true);
            }

            $attributes = [
                'subscription_plan_id' => $plan->id, 'status' => 'active',
                'trial_ends_at' => null, 'current_period_start' => now(), 'current_period_end' => null,
                'canceled_at' => null, 'cancel_at_period_end' => false,
                'price_minor' => 0, 'currency' => $plan->currency, 'interval' => $plan->interval,
            ];

            return $active
                ? tap($active)->update($attributes)
                : $org->subscription()->create($attributes);
        });
    }

    /**
     * The open invoice to pay for a paid plan. Its subscription stays "incomplete", and
     * grants nothing, until activate() runs. Asking again for the same plan reuses the
     * unpaid invoice rather than piling up new ones.
     */
    public function checkout(Organization $org, SubscriptionPlan $plan): SubscriptionInvoice
    {
        if ($plan->isFree()) {
            throw new RuntimeException('The free plan does not need payment.');
        }

        return DB::transaction(function () use ($org, $plan) {
            $pending = $org->subscription()
                ->where('status', 'incomplete')
                ->where('subscription_plan_id', $plan->id)
                ->lockForUpdate()
                ->first();

            $subscription = $pending ?? $org->subscription()->create([
                'subscription_plan_id' => $plan->id, 'status' => 'incomplete',
                'price_minor' => $plan->price_minor, 'currency' => $plan->currency, 'interval' => $plan->interval,
            ]);

            $invoice = $subscription->invoices()->where('status', 'open')->first();

            // A price change since the last attempt is billed at the current price.
            if ($invoice && $invoice->amount_minor !== $plan->price_minor) {
                $invoice->update(['status' => 'void']);
                $invoice = null;
            }

            return $invoice ?? $subscription->invoices()->create([
                'number' => 'INV-'.strtoupper(Str::random(10)),
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'status' => 'open',
            ]);
        });
    }

    /**
     * Switch the paid plan on for a verified invoice payment. Idempotent. Renewing the
     * plan already running extends it from the end of the current period; any other
     * plan starts now and replaces the old one.
     */
    public function activate(SubscriptionInvoice $invoice): Subscription
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SubscriptionInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $subscription = $invoice->subscription()->with('organization')->firstOrFail();

            if ($invoice->status === 'paid') {
                return $subscription;
            }

            $org = $subscription->organization;
            $current = $org->activeSubscription();
            $renewing = $current && $current->id !== $subscription->id
                && $current->subscription_plan_id === $subscription->subscription_plan_id
                && $current->current_period_end?->isFuture();

            $start = $renewing ? $current->current_period_end->copy() : now();
            $end = $this->periodEnd($start->copy(), $subscription->interval);

            $org->subscription()
                ->whereKeyNot($subscription->id)
                ->whereIn('status', ['trialing', 'active'])
                ->update(['status' => 'canceled', 'canceled_at' => now(), 'cancel_at_period_end' => false]);

            $subscription->update([
                'status' => 'active', 'trial_ends_at' => null,
                'current_period_start' => $start, 'current_period_end' => $end,
                'canceled_at' => null, 'cancel_at_period_end' => false,
            ]);

            $invoice->update(['status' => 'paid', 'paid_at' => now(), 'period_start' => $start, 'period_end' => $end]);

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

    /** Whether the org may publish another event under its current plan's limit. */
    public function canPublishEvents(Organization $org): bool
    {
        $plan = $org->currentPlan();
        if (! $plan) {
            return false;
        }

        if ($plan->event_limit === null) {
            return true;
        }

        return $this->eventsCountedAgainstLimit($org) < $plan->event_limit;
    }

    /**
     * Events using up the plan's event limit: live events, or for a plan with a lifetime
     * limit (Starter's one free event) every event that was ever published.
     */
    public function eventsCountedAgainstLimit(Organization $org): int
    {
        return $org->currentPlan()?->lifetime_event_limit
            ? $org->events()->withTrashed()->whereNotNull('published_at')->count()
            : $org->events()->where('status', 'published')->count();
    }

    /**
     * Upcoming events switched on for USSD sales against the plan limit (null = unlimited,
     * 0 = USSD is not part of the plan). An event frees its slot once it starts.
     *
     * @return array{used: int, limit: int|null}
     */
    public function ussdEvents(Organization $org): array
    {
        $plan = $org->currentPlan();

        return [
            'used' => $org->events()->where('sell_on_ussd', true)->upcoming()->count(),
            'limit' => $plan ? $plan->ussd_event_limit : 0,
        ];
    }

    /** Whether the org may switch USSD sales on for another upcoming event. */
    public function canAddUssdEvent(Organization $org): bool
    {
        ['used' => $used, 'limit' => $limit] = $this->ussdEvents($org);

        return $limit === null || $used < $limit;
    }

    /**
     * Whether the org may invite another team member. Members above the limit
     * after a downgrade are kept, but no new invites go out until they fit.
     */
    public function canAddTeamMember(Organization $org): bool
    {
        if (! $org->currentPlan()) {
            return false;
        }

        ['used' => $used, 'limit' => $limit] = $this->teamSeats($org);

        return $limit === null || $used < $limit;
    }

    /**
     * Team seats in use against the plan limit (null = unlimited). Pending
     * invites take a seat until they expire, so the limit can't be bypassed by
     * mass-inviting; event-only gate staff are separate and never count.
     *
     * @return array{used: int, limit: int|null}
     */
    public function teamSeats(Organization $org): array
    {
        $used = $org->members()
            ->where(fn ($q) => $q->whereNotNull('accepted_at')
                ->orWhere('invited_at', '>', now()->subDays(OrganizationMember::INVITE_TTL_DAYS)))
            ->count();

        $plan = $org->currentPlan();

        return ['used' => $used, 'limit' => $plan ? $plan->team_member_limit : 1];
    }

    private function periodEnd(Carbon $from, string $interval): Carbon
    {
        return $interval === 'yearly' ? $from->addYear() : $from->addMonth();
    }
}
