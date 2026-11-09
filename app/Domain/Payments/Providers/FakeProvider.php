<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\PaymentIntent;
use App\Domain\Payments\VerificationResult;
use App\Models\Payment;
use RuntimeException;

/**
 * Deterministic provider for local development and automated tests ONLY.
 * It must never run in production — simulating settled money there is forbidden.
 */
class FakeProvider implements PaymentProvider
{
    public function __construct()
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The fake payment provider cannot be used in production.');
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    public function initiate(Payment $payment, string $returnUrl): PaymentIntent
    {
        // No external redirect; the buyer is sent straight to the return URL,
        // which triggers server-side verification like a real provider callback.
        return new PaymentIntent(
            providerRef: 'FAKE-'.$payment->reference,
            redirectUrl: $returnUrl,
        );
    }

    public function verify(string $providerRef): VerificationResult
    {
        // In dev/test a verification always "succeeds" for the exact order amount,
        // which is resolved by the caller from the Payment record.
        return new VerificationResult(
            successful: true,
            amountMinor: 0,          // caller cross-checks against the Payment amount
            currency: 'UGX',
            processorFeeMinor: 0,
            method: 'mobile_money',
            raw: ['provider' => 'fake', 'ref' => $providerRef],
        );
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        return true;
    }

    public function webhookReference(array $payload): ?string
    {
        return $payload['ref'] ?? $payload['data']['tx_ref'] ?? null;
    }
}
