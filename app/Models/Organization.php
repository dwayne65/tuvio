<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'logo_path', 'website',
        'support_email', 'support_phone', 'country', 'default_currency',
        'status', 'verification_status', 'verified_at', 'commission_bps',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'commission_bps' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(OrganizationPayoutAccount::class);
    }

    public function subscription(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscription()
            ->whereIn('status', ['trialing', 'active'])
            ->latest('current_period_end')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }
}
