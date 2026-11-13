<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\PaymentIntent;
use App\Domain\Payments\VerificationResult;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Flutterwave adapter (sandbox-ready). Supports Mobile Money and card via the
 * hosted Standard checkout. All verification is server-side against the v3 API.
 */
class FlutterwaveProvider implements PaymentProvider
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'flutterwave';
    }

    public function initiate(Payment $payment, string $returnUrl): PaymentIntent
    {
        $response = Http::withToken($this->config['secret_key'])
            ->acceptJson()
            ->post("{$this->config['base_url']}/payments", [
                'tx_ref' => $payment->reference,
                'amount' => $payment->amount_minor / 100,
                'currency' => $payment->currency,
                'redirect_url' => $returnUrl,
                'payment_options' => 'mobilemoneyuganda,card',
                'customer' => [
                    'email' => $payment->order?->buyer_email ?? 'customer@tuvio.app',
                    'name' => $payment->order?->buyer_name ?? 'Customer',
                ],
                'meta' => ['payment_id' => $payment->id],
            ]);

        if (! $response->successful() || ($response->json('status') !== 'success')) {
            throw new RuntimeException('Failed to initiate Flutterwave payment: '.$response->body());
        }

        return new PaymentIntent(
            providerRef: $payment->reference,
            redirectUrl: $response->json('data.link'),
        );
    }

    public function verify(string $providerRef): VerificationResult
    {
        // Resolve by tx_ref then verify by transaction id.
        $lookup = Http::withToken($this->config['secret_key'])
            ->get("{$this->config['base_url']}/transactions", ['tx_ref' => $providerRef]);

        $txn = $lookup->json('data.0');
        if (! $txn) {
            return new VerificationResult(false, 0, 'UGX');
        }

        $verify = Http::withToken($this->config['secret_key'])
            ->get("{$this->config['base_url']}/transactions/{$txn['id']}/verify");

        $data = $verify->json('data') ?? [];
        $successful = ($verify->json('status') === 'success') && (($data['status'] ?? null) === 'successful');

        return new VerificationResult(
            successful: $successful,
            amountMinor: (int) round(($data['amount'] ?? 0) * 100),
            currency: $data['currency'] ?? 'UGX',
            processorFeeMinor: (int) round(($data['app_fee'] ?? 0) * 100),
            method: $data['payment_type'] ?? null,
            raw: $data,
        );
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;

        return $secret !== null && hash_equals($secret, (string) $signature);
    }

    public function webhookReference(array $payload): ?string
    {
        return $payload['data']['tx_ref'] ?? $payload['txRef'] ?? null;
    }
}
