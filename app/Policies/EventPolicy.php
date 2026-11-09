<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization);
    }

    public function create(User $user, Organization $org): bool
    {
        return $user->canManageOrganization($org);
    }

    public function update(User $user, Event $event): bool
    {
        return in_array($user->roleIn($event->organization), ['owner', 'admin', 'manager'], true);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->canManageOrganization($event->organization);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->canManageOrganization($event->organization);
    }

    /** Owner/admin/manager/staff may scan tickets at the gate. */
    public function checkIn(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization)
            || $event->staff()->where('user_id', $user->id)->exists();
    }
}
