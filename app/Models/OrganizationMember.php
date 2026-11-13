<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team member, or a pending invite when accepted_at is null. A pending
 * invite has no user_id until accepted if the invitee had no account yet.
 */
class OrganizationMember extends Model
{
    /** Days an invite link stays valid after it was (re)sent. */
    public const INVITE_TTL_DAYS = 7;

    protected $fillable = [
        'organization_id', 'user_id', 'role',
        'invite_email', 'invite_token', 'invited_at', 'accepted_at',
    ];

    protected $hidden = ['invite_token'];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function inviteExpired(): bool
    {
        return $this->invited_at === null || $this->invited_at->copy()->addDays(self::INVITE_TTL_DAYS)->isPast();
    }
}
