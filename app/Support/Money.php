<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Immutable money value object. All amounts are stored and passed around as
 * integer MINOR units (e.g. cents) to avoid floating-point rounding errors in
 * the financial ledger. Currencies with no minor unit (e.g. UGX) simply treat
 * the minor unit as equal to the major unit.
 */
final class Money
{
    public function __construct(
        public readonly int $minor,
        public readonly string $currency = 'UGX',
    ) {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Currency must be a 3-letter code, got [{$currency}].");
        }
    }

    public static function of(int $minor, string $currency = 'UGX'): self
    {
        return new self($minor, strtoupper($currency));
    }

    public static function zero(string $currency = 'UGX'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /** Apply a rate expressed in basis points (500 = 5.00%), rounding half-up. */
    public function percentageBps(int $bps): self
    {
        $result = (int) round($this->minor * $bps / 10000);

        return new self($result, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(Money $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /** Human-readable major-unit value, e.g. 150000 -> "1,500.00". */
    public function formatted(int $decimals = 2): string
    {
        return number_format($this->minor / (10 ** $decimals), $decimals);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}."
            );
        }
    }
}
