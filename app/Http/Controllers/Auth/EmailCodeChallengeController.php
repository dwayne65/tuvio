<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\EmailLoginCode;
use App\Domain\Auth\PendingLogin;
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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/** Login step for admins and Professional/Enterprise members: the code emailed to them. */
class EmailCodeChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 10;

    public function __construct(private EmailLoginCode $loginCodes) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/EmailCodeChallenge', [
            'email' => $this->maskEmail($user->email),
            'ttlMinutes' => (int) config('ticketflow.email_login_codes.ttl_minutes'),
            'resendSeconds' => (int) config('ticketflow.email_login_codes.resend_seconds'),
            'hasAuthenticator' => $user->hasTwoFactorEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('error', 'Your sign-in expired. Please sign in again.');
        }

        $key = "email-code:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            PendingLogin::abandon($request);
            $this->audit($request, $user, 'auth.email_code_locked');

            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        if (! $this->loginCodes->verify($user, $validated['code'])) {
            RateLimiter::hit($key, 900);
            $this->audit($request, $user, 'auth.email_code_failed');

            throw ValidationException::withMessages([
                'code' => $this->loginCodes->isPending($user)
                    ? 'That code is not right. Check the latest email and try again.'
                    : 'That code has expired or was used up. Send yourself a new one.',
            ]);
        }

        RateLimiter::clear($key);
        PendingLogin::markEmailCodePassed($request);

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.login');
        }

        return PendingLogin::complete($request, $user);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('error', 'Your sign-in expired. Please sign in again.');
        }

        try {
            $sent = $this->loginCodes->send($user);
        } catch (TransportExceptionInterface $e) {
            report($e);

            return back()->with('error', 'We could not send the email just now. Please try again in a minute.');
        }

        return $sent
            ? back()->with('success', 'A new code is on its way. Only the latest code works.')
            : back()->with('error', 'A code was sent moments ago. Give it a minute to arrive before asking again.');
    }

    private function pendingUser(Request $request): ?User
    {
        return PendingLogin::awaitsEmailCode($request) ? PendingLogin::user($request) : null;
    }

    /** "jo•••@gmail.com", so the page confirms which inbox to check without revealing it in full. */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 2).str_repeat('•', max(3, Str::length($local) - 2)).'@'.$domain;
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
