<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionPlan;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a dashboard feature unless the organization's current, paid-up plan includes
 * it, e.g. `plan:promo_codes`. Enforced on the server so a locked feature cannot be
 * reached by typing its URL or replaying a request.
 */
class EnsurePlanAllows
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $org = CurrentOrganization::resolve($request);

        if (! $org || $org->planAllows($capability)) {
            return $next($request);
        }

        $feature = SubscriptionPlan::CAPABILITIES[$capability] ?? 'This feature';
        $plan = SubscriptionPlan::cheapestWith($capability);
        $message = $plan
            ? "{$feature} is part of the {$plan->name} plan. Upgrade to unlock it."
            : "{$feature} is not included in your plan.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('subscription.index')->with('error', $message);
    }
}
