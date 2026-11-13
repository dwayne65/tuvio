<?php

namespace App\Domain\Payments;

/** Outcome of a server-side payment verification. */
final class VerificationResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly int $processorFeeMinor = 0,
        public readonly ?string $method = null,
        public readonly array $raw = [],
        // Not paid yet but not failed either (e.g. waiting for the buyer's Mobile Money PIN).
        public readonly bool $pending = false,
    ) {}
}
