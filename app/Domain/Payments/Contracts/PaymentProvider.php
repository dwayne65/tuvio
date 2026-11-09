<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\PaymentIntent;
use App\Domain\Payments\VerificationResult;
use App\Models\Payment;

interface PaymentProvider
{
    public function name(): string;

    /** Begin a charge; returns where/how the buyer completes payment. */
    public function initiate(Payment $payment, string $returnUrl): PaymentIntent;

    /** Server-side verification of a transaction by its provider reference. */
    public function verify(string $providerRef): VerificationResult;

    /** Validate a webhook payload's authenticity. */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;

    /** Extract the provider reference a webhook refers to. */
    public function webhookReference(array $payload): ?string;
}
