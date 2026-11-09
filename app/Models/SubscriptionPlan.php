<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price_minor', 'currency', 'interval',
        'trial_days', 'event_limit', 'ticket_limit', 'team_member_limit',
        'features', 'is_active', 'is_public', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'trial_days' => 'integer',
            'event_limit' => 'integer',
            'ticket_limit' => 'integer',
            'team_member_limit' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUnlimitedEvents(): bool
    {
        return $this->event_limit === null;
    }
}
