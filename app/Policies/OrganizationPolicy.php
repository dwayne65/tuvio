<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org);
    }

    public function update(User $user, Organization $org): bool
    {
        return $user->canManageOrganization($org);
    }

    public function manageTeam(User $user, Organization $org): bool
    {
        return $user->canManageOrganization($org);
    }

    /** Discounts, promoters and campaigns. */
    public function manageMarketing(User $user, Organization $org): bool
    {
        return $user->roleIn($org)?->canManageMarketing() ?? false;
    }

    public function manageFinances(User $user, Organization $org): bool
    {
        return $user->roleIn($org)?->canManageFinances() ?? false;
    }

    public function requestPayout(User $user, Organization $org): bool
    {
        return $user->roleIn($org)?->canRequestPayouts() ?? false;
    }
}
