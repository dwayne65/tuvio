<?php

namespace App\Models;

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

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar_path',
        'locale', 'country', 'timezone', 'is_admin',
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

    /** Every organization the user belongs to (owner or member). */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role', 'accepted_at')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /** The user's role within a given organization, or null if not a member. */
    public function roleIn(Organization $organization): ?string
    {
        return $this->memberships
            ->firstWhere('organization_id', $organization->id)?->role;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->roleIn($organization) !== null;
    }

    /** Owner/admin can manage org settings, events, finances. */
    public function canManageOrganization(Organization $organization): bool
    {
        return in_array($this->roleIn($organization), ['owner', 'admin'], true);
    }

    public function isOrganizer(): bool
    {
        return $this->memberships()->exists() || $this->ownedOrganizations()->exists();
    }
}
