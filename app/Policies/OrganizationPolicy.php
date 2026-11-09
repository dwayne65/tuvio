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

    public function manageFinances(User $user, Organization $org): bool
    {
        return in_array($user->roleIn($org), ['owner', 'admin', 'finance'], true);
    }

    public function requestPayout(User $user, Organization $org): bool
    {
        return in_array($user->roleIn($org), ['owner', 'finance'], true);
    }
}
