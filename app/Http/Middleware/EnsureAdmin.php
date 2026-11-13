<?php

namespace App\Http\Middleware;

use App\Domain\Auth\PendingLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin area needs more than a password: either the code emailed at sign-in or an
 * authenticator app. A session that skipped the emailed code (e.g. a "remember me"
 * cookie) is signed out so the admin signs in again and gets a fresh code.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $request->user()->is_admin, 403);

        if ($request->user()->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (config('ticketflow.email_login_codes.enabled')) {
            if ($request->session()->has(PendingLogin::SECOND_STEP_KEY)) {
                return $next($request);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Please sign in again. We will email you a code to confirm it is you.');
        }

        // With emailed codes switched off, an authenticator app is the only second step left.
        return redirect()->route('profile.edit')
            ->with('error', 'Turn on two-factor authentication to access the admin area.');
    }
}
