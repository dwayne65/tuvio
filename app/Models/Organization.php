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
            'approved_at' => 'datetime',
            'commission_bps' => 'integer',
        ];
    }

    /** Statuses: pending (awaiting admin approval), active, rejected, suspended. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

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

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function promoters(): HasMany
    {
        return $this->hasMany(Promoter::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /** The paid-up subscription whose period is still running, if any. */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscription()
            ->whereIn('status', ['trialing', 'active'])
            ->where(fn ($query) => $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now()))
            ->latest('current_period_end')
            ->first();
    }

    /**
     * The plan whose limits, features and commission apply right now: the active
     * subscription's plan, otherwise the free plan. Null only if no free plan exists.
     */
    public function currentPlan(): ?SubscriptionPlan
    {
        return $this->activeSubscription()?->plan ?? SubscriptionPlan::free();
    }

    /** Platform commission on ticket sales: an admin-negotiated rate, then the plan's, then the default. */
    public function commissionBps(): int
    {
        return (int) ($this->commission_bps ?? $this->currentPlan()?->commission_bps ?? config('ticketflow.commission_bps'));
    }

    public function planAllows(string $capability): bool
    {
        return $this->currentPlan()?->allows($capability) ?? false;
    }

    /** Only an organization approved by a platform admin may use the organizer dashboard. */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }
}
