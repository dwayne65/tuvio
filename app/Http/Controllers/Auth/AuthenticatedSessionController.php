<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\EmailLoginCode;
use App\Domain\Auth\PendingLogin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthenticatedSessionController extends Controller
{
    /** Failed attempts allowed for one account from one IP before a short lockout. */
    private const ATTEMPTS_PER_DEVICE = 5;

    /** Failed attempts allowed for one account across every IP, against distributed guessing. */
    private const ATTEMPTS_PER_ACCOUNT = 20;

    private const ACCOUNT_LOCKOUT_SECONDS = 900;

    public function __construct(private EmailLoginCode $loginCodes) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
            'email' => $request->string('email')->limit(255, '')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->deviceKey($request), 60);
            RateLimiter::hit($this->accountKey($request), self::ACCOUNT_LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->deviceKey($request));
        RateLimiter::clear($this->accountKey($request));

        /** @var User $user */
        $user = Auth::getLastAttempted();

        $needsEmailCode = $user->requiresEmailLoginCode();

        if (! $needsEmailCode && ! $user->hasTwoFactorEnabled()) {
            PendingLogin::start($request, $user, $request->boolean('remember'), emailCodeRequired: false);

            return PendingLogin::complete($request, $user);
        }

        PendingLogin::start($request, $user, $request->boolean('remember'), $needsEmailCode);

        if (! $needsEmailCode) {
            return redirect()->route('two-factor.login');
        }

        try {
            $this->loginCodes->send($user);
        } catch (TransportExceptionInterface $e) {
            report($e);
            PendingLogin::abandon($request);

            throw ValidationException::withMessages([
                'email' => 'We could not email your sign-in code just now. Please try again in a minute.',
            ]);
        }

        return redirect()->route('login.email-code');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        $deviceLocked = RateLimiter::tooManyAttempts($this->deviceKey($request), self::ATTEMPTS_PER_DEVICE);
        $accountLocked = RateLimiter::tooManyAttempts($this->accountKey($request), self::ATTEMPTS_PER_ACCOUNT);

        if (! $deviceLocked && ! $accountLocked) {
            return;
        }

        event(new Lockout($request));

        AuditLog::create([
            'user_id' => User::where('email', $request->input('email'))->value('id'),
            'action' => $accountLocked ? 'auth.account_locked' : 'auth.login_throttled',
            'new_values' => ['email' => Str::limit((string) $request->input('email'), 255, '')],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        $seconds = max(
            RateLimiter::availableIn($this->deviceKey($request)),
            $accountLocked ? RateLimiter::availableIn($this->accountKey($request)) : 0,
        );

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function deviceKey(Request $request): string
    {
        return 'login:'.Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
    }

    private function accountKey(Request $request): string
    {
        return 'login-account:'.Str::transliterate(Str::lower($request->string('email')));
    }
}
