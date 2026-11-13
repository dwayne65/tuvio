<?php

namespace App\Domain\Auth;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Six-digit sign-in codes sent by email. Only a keyed hash of the code is kept, it
 * expires after a few minutes, and a handful of wrong guesses burns it so the user
 * has to ask for a new one.
 */
class EmailLoginCode
{
    /**
     * Email a fresh code, replacing any earlier one. Returns false without sending when
     * a code went out moments ago, so the resend button cannot be used to spam the inbox.
     */
    public function send(User $user): bool
    {
        if (! Cache::add($this->cooldownKey($user), true, (int) config('ticketflow.email_login_codes.resend_seconds'))) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('ticketflow.email_login_codes.ttl_minutes');

        Cache::put($this->codeKey($user), ['hash' => $this->hash($user, $code), 'attempts' => 0], now()->addMinutes($minutes));

        Mail::to($user)->send(new LoginCodeMail($user, $code, $minutes));

        return true;
    }

    /** Check a code; a correct one is consumed and can never be used again. */
    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        $stored = Cache::get($this->codeKey($user));

        if (! $stored || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        if (hash_equals($stored['hash'], $this->hash($user, $code))) {
            Cache::forget($this->codeKey($user));

            return true;
        }

        $stored['attempts']++;
        if ($stored['attempts'] >= (int) config('ticketflow.email_login_codes.max_attempts')) {
            Cache::forget($this->codeKey($user));
        } else {
            Cache::put($this->codeKey($user), $stored, now()->addMinutes((int) config('ticketflow.email_login_codes.ttl_minutes')));
        }

        return false;
    }

    /** Whether a usable code is waiting for this user. */
    public function isPending(User $user): bool
    {
        return Cache::has($this->codeKey($user));
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', "{$user->id}|{$code}", (string) config('app.key'));
    }

    private function codeKey(User $user): string
    {
        return "email-login-code:{$user->id}";
    }

    private function cooldownKey(User $user): string
    {
        return "email-login-code-sent:{$user->id}";
    }
}
