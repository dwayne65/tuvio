<?php

namespace App\Domain\Payments;

/** Instructions for completing a payment (a redirect URL, or inline next steps). */
final class PaymentIntent
{
    public function __construct(
        public readonly string $providerRef,
        public readonly ?string $redirectUrl = null,
        public readonly array $meta = [],
    ) {}
}
