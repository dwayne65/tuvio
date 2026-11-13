<?php

namespace App\Domain\Auth;

use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Time-based one-time passwords (RFC 6238) for authenticator apps such as
 * Google Authenticator, Authy or 1Password, plus single-use recovery codes.
 */
class TwoFactorAuthenticator
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    /** Accept one step either side to tolerate small clock drift on the phone. */
    private const WINDOW = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $label = rawurlencode(config('app.name').':'.$user->email);
        $url = "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode(config('app.name'))
            .'&digits='.self::DIGITS.'&period='.self::PERIOD;

        return (new SvgWriter)->write(new QrCode(data: $url, size: 220, margin: 0))->getString();
    }

    /** Verify a code against a secret; each accepted code can be used only once. */
    public function verify(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }

        $currentStep = intdiv(now()->getTimestamp(), self::PERIOD);

        foreach (range(-self::WINDOW, self::WINDOW) as $offset) {
            $step = $currentStep + $offset;

            if (hash_equals($this->codeAt($secret, $step), $code)) {
                // Cache::add is atomic: a replayed code inside its validity window is refused.
                return Cache::add("two-factor-used:{$user->id}:{$step}", true, self::PERIOD * (self::WINDOW * 2 + 2));
            }
        }

        return false;
    }

    /** Consume a recovery code if it matches; it cannot be used again. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ? json_decode($user->two_factor_recovery_codes, true) : [];
        $code = Str::lower(trim($code));

        foreach ($codes as $index => $candidate) {
            if (hash_equals($candidate, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($codes))])->save();

                return true;
            }
        }

        return false;
    }

    public function codeAt(string $secret, int $timestep): string
    {
        $hash = hash_hmac('sha1', pack('J', $timestep), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return collect(str_split($bits, 5))
            ->map(fn (string $chunk) => self::BASE32[bindec(str_pad($chunk, 5, '0'))])
            ->implode('');
    }

    private function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $char) {
            $bits .= str_pad(decbin(strpos(self::BASE32, $char)), 5, '0', STR_PAD_LEFT);
        }

        return collect(str_split($bits, 8))
            ->filter(fn (string $chunk) => strlen($chunk) === 8)
            ->map(fn (string $chunk) => chr(bindec($chunk)))
            ->implode('');
    }
}
