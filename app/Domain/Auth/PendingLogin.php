<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A sign-in that passed the password check but still owes extra steps: an emailed
 * code, an authenticator-app code, or both (email first). Held in the session for a
 * few minutes; the user is only logged in once every required step has passed.
 */
class PendingLogin
{
    /** Authenticator codes are at hand; emails can take a little longer to arrive. */
    private const MINUTES = 5;

    private const MINUTES_WITH_EMAIL_CODE = 15;

    public const SECOND_STEP_KEY = 'auth.second_step_at';

    private const KEYS = [
        'login.id', 'login.remember', 'login.expires_at',
        'login.email_code_required', 'login.email_code_passed',
    ];

    public static function start(Request $request, User $user, bool $remember, bool $emailCodeRequired): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            'login.id' => $user->id,
            'login.remember' => $remember,
            'login.expires_at' => now()->addMinutes($emailCodeRequired ? self::MINUTES_WITH_EMAIL_CODE : self::MINUTES)->getTimestamp(),
            'login.email_code_required' => $emailCodeRequired,
            'login.email_code_passed' => false,
        ]);
    }

    /** The user part-way through signing in, or null if there is none or it expired. */
    public static function user(Request $request): ?User
    {
        $id = $request->session()->get('login.id');
        $expiresAt = (int) $request->session()->get('login.expires_at', 0);

        if (! $id || $expiresAt < now()->getTimestamp()) {
            return null;
        }

        return User::find($id);
    }

    public static function awaitsEmailCode(Request $request): bool
    {
        return $request->session()->get('login.email_code_required', false)
            && ! $request->session()->get('login.email_code_passed', false);
    }

    public static function markEmailCodePassed(Request $request): void
    {
        $request->session()->put('login.email_code_passed', true);
    }

    /** Every step passed: log the user in and send them where they were going. */
    public static function complete(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->get('login.remember', false);
        $passedSecondStep = $request->session()->get('login.email_code_passed', false) || $user->hasTwoFactorEnabled();
        self::abandon($request);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Remembered so the admin area can tell this session proved more than the password.
        if ($passedSecondStep) {
            $request->session()->put(self::SECOND_STEP_KEY, now()->getTimestamp());
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return self::redirectAfterSignIn($request, $user);
    }

    /**
     * Back to the page the user was trying to reach, unless it is the admin area and
     * they are not an admin: following it would only land them on a 403.
     */
    public static function redirectAfterSignIn(Request $request, User $user): RedirectResponse
    {
        $intended = (string) $request->session()->get('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

        if (! $user->isAdmin() && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }

    public static function abandon(Request $request): void
    {
        $request->session()->forget(self::KEYS);
    }
}
