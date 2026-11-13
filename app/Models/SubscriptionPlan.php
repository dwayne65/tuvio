<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /** Dashboard features a plan can unlock, keyed by capability with the name organizers see. */
    public const CAPABILITIES = [
        'advanced_analytics' => 'Advanced analytics',
        'promo_codes' => 'Promo codes and discounts',
        'promoters' => 'Promoter referral links',
        'attendee_export' => 'Attendee list export',
        'reports' => 'Financial reports and exports',
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'price_minor', 'currency', 'commission_bps', 'interval',
        'trial_days', 'event_limit', 'lifetime_event_limit', 'ticket_limit', 'team_member_limit', 'ussd_event_limit',
        'features', 'capabilities', 'is_active', 'is_public', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'commission_bps' => 'integer',
            'trial_days' => 'integer',
            'event_limit' => 'integer',
            'lifetime_event_limit' => 'boolean',
            'ticket_limit' => 'integer',
            'team_member_limit' => 'integer',
            'ussd_event_limit' => 'integer',
            'features' => 'array',
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** The free plan every organization falls back to when it has no paid plan running. */
    public static function free(): ?self
    {
        return static::where('is_active', true)->where('price_minor', 0)->orderBy('sort')->first();
    }

    /** The cheapest active plan that unlocks a capability, to name in upgrade prompts. */
    public static function cheapestWith(string $capability): ?self
    {
        return static::where('is_active', true)->orderBy('sort')->get()
            ->first(fn (self $plan) => $plan->allows($capability));
    }

    public function isFree(): bool
    {
        return $this->price_minor === 0;
    }

    public function allows(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function isUnlimitedEvents(): bool
    {
        return $this->event_limit === null;
    }
}
