<?php

namespace App\Enums;

/**
 * A member's role within an organization. Every permission check for
 * organization members goes through these methods, so the access matrix
 * lives in one place.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Finance = 'finance';
    case Marketing = 'marketing';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Manager => 'Event manager',
            self::Finance => 'Finance',
            self::Marketing => 'Marketing',
            self::Staff => 'Staff',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including billing and payouts.',
            self::Admin => 'Everything except requesting payouts.',
            self::Manager => 'Creates and edits events, discounts and promoters.',
            self::Finance => 'Transactions, payouts and reports.',
            self::Marketing => 'Discounts, promoters and campaigns.',
            self::Staff => 'Scans tickets at every event.',
        };
    }

    /** Org settings, billing, team, publishing and deleting events. */
    public function canManageOrganization(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageEvents(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager], true);
    }

    /** Discounts, promoters and campaigns. */
    public function canManageMarketing(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager, self::Marketing], true);
    }

    public function canManageFinances(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance], true);
    }

    public function canRequestPayouts(): bool
    {
        return in_array($this, [self::Owner, self::Finance], true);
    }

    /**
     * Roles that can be given through an invite or a role change. Ownership
     * is never handed out this way.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role !== self::Owner));
    }
}
