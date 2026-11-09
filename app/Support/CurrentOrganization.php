<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Http\Request;

/** Resolves the active organization for the signed-in user from the session. */
class CurrentOrganization
{
    public static function resolve(Request $request): ?Organization
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $id = $request->session()->get('current_organization_id');

        if ($id) {
            $org = Organization::find($id);
            if ($org && $user->belongsToOrganization($org)) {
                return $org;
            }
        }

        // Fall back to the user's first organization (owned or member).
        $org = $user->organizations()->first() ?? $user->ownedOrganizations()->first();
        if ($org) {
            $request->session()->put('current_organization_id', $org->id);
        }

        return $org;
    }
}
