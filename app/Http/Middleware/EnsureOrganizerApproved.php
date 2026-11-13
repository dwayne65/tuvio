<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the organizer dashboard closed until a platform admin has approved the
 * organization, so throwaway sign-ups cannot create or sell events. Gate scanning
 * stays open: event staff may be scanning for another, approved organizer.
 */
class EnsureOrganizerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = CurrentOrganization::resolve($request);

        if (! $org || $org->isApproved() || $request->routeIs('dashboard.check-in.*')) {
            return $next($request);
        }

        $message = $org->isAwaitingApproval()
            ? 'Your organizer account is waiting for approval. We will email you as soon as it is ready.'
            : 'Your organizer account is not active. Contact support for help.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }
}
