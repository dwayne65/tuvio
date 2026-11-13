<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\PendingLogin;
use App\Domain\Auth\TwoFactorAuthenticator;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Second login step for accounts with two-factor authentication enabled. */
class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private TwoFactorAuthenticator $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (PendingLogin::user($request) && PendingLogin::awaitsEmailCode($request)) {
            return redirect()->route('login.email-code');
        }

        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:10', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('error', 'Your sign-in expired. Please sign in again.');
        }

        $key = "two-factor:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            // Too many wrong codes: abandon the half-finished login entirely.
            PendingLogin::abandon($request);
            $this->audit($request, $user, 'auth.two_factor_locked');

            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $passed = filled($validated['code'] ?? null)
            ? $this->twoFactor->verify($user, $user->two_factor_secret, $validated['code'])
            : $this->twoFactor->useRecoveryCode($user, $validated['recovery_code']);

        if (! $passed) {
            RateLimiter::hit($key, 300);
            $this->audit($request, $user, 'auth.two_factor_failed');

            throw ValidationException::withMessages([
                filled($validated['code'] ?? null) ? 'code' : 'recovery_code' => 'That code is invalid. Please try again.',
            ]);
        }

        RateLimiter::clear($key);

        return PendingLogin::complete($request, $user);
    }

    private function pendingUser(Request $request): ?User
    {
        // The emailed code, when required, comes before the authenticator code.
        if (PendingLogin::awaitsEmailCode($request)) {
            return null;
        }

        $user = PendingLogin::user($request);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    private function audit(Request $request, User $user, string $action): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
