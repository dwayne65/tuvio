<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** is_admin and the two_factor_* columns are deliberately not mass assignable. */
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar_path',
        'locale', 'country', 'timezone',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    /** Organizations this user owns. */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /** Organization memberships (as owner/admin/manager/staff). */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Tickets the user bought, through their orders (the ticket wallet). */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, Order::class);
    }

    public function savedEvents(): HasMany
    {
        return $this->hasMany(SavedEvent::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** Every organization the user belongs to (owner or member). Pending invites are excluded. */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role', 'accepted_at')
            ->wherePivotNotNull('accepted_at')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /** Two-factor authentication counts only once the user has confirmed a code from their app. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Whether signing in needs a one-time code sent to the user's email: always for
     * platform admins, and for members of an organization on a plan that requires it
     * (Professional and Enterprise by default).
     */
    public function requiresEmailLoginCode(): bool
    {
        if (! config('ticketflow.email_login_codes.enabled')) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $plans = config('ticketflow.email_login_codes.plans', []);

        return $this->organizations()->get()
            ->contains(fn (Organization $organization): bool => in_array($organization->currentPlan()?->slug, $plans, true));
    }

    /** The user's role within a given organization, or null if not a member (a pending invite is not membership). */
    public function roleIn(Organization $organization): ?OrganizationRole
    {
        return $this->memberships
            ->first(fn (OrganizationMember $member): bool => $member->organization_id === $organization->id && $member->isAccepted())
            ?->role;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->roleIn($organization) !== null;
    }

    /** Owner/admin can manage org settings, events, finances. */
    public function canManageOrganization(Organization $organization): bool
    {
        return $this->roleIn($organization)?->canManageOrganization() ?? false;
    }

    public function isOrganizer(): bool
    {
        return $this->memberships()->accepted()->exists() || $this->ownedOrganizations()->exists();
    }
}
