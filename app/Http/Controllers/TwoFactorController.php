<?php

namespace App\Http\Controllers;

use App\Domain\Auth\TwoFactorAuthenticator;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Account settings: enrol, confirm, and remove two-factor authentication. */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorAuthenticator $twoFactor) {}

    /** Start enrolment: issue a new secret that must be confirmed with a code. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => json_encode($this->twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Scan the QR code with your authenticator app, then enter the 6-digit code.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->twoFactor->verify($user, $user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is invalid. Check your phone\'s time and try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->audit($request, 'auth.two_factor_enabled');

        return back()
            ->with('success', 'Two-factor authentication is on. Save your recovery codes somewhere safe.')
            ->with('twoFactorRecoveryCodes', json_decode($user->two_factor_recovery_codes, true));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $codes = $this->twoFactor->generateRecoveryCodes();

        $request->user()->forceFill(['two_factor_recovery_codes' => json_encode($codes)])->save();

        return back()->with('success', 'New recovery codes generated. The old ones no longer work.')
            ->with('twoFactorRecoveryCodes', $codes);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->audit($request, 'auth.two_factor_disabled');

        return back()->with('success', 'Two-factor authentication is off.');
    }

    private function audit(Request $request, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
