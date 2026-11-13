<?php

namespace Tests\Unit;

use App\Domain\Auth\TwoFactorAuthenticator;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class TwoFactorAuthenticatorTest extends TestCase
{
    /** RFC 6238 Appendix B SHA-1 vectors (secret "12345678901234567890"), truncated to 6 digits. */
    #[TestWith([59, '287082'])]
    #[TestWith([1111111109, '081804'])]
    #[TestWith([1234567890, '005924'])]
    #[TestWith([20000000000, '353130'])]
    public function test_codes_match_the_rfc_6238_reference_vectors(int $timestamp, string $expected): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame($expected, (new TwoFactorAuthenticator)->codeAt($secret, intdiv($timestamp, 30)));
    }
}
