<?php

namespace App\Domain\Payouts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paytota disbursement API (gate.paytota.com). A payout is created first (POST payouts/),
 * then executed against the execution_url it returns, which moves the money; the final
 * outcome arrives on the dashboard webhook as status "success" or "error".
 */
class PaytotaPayouts
{
    /** Final payout statuses, mapped to TUVIO's payout statuses. */
    private const OUTCOMES = [
        'success' => 'paid',
        'paid' => 'paid',
        'error' => 'failed',
        'failed' => 'failed',
        'cancelled' => 'failed',
        'rejected' => 'failed',
    ];

    public function __construct(private array $config) {}

    /**
     * Step 1: register the payout. Nothing is sent yet, so any failure here is a rejection.
     *
     * @param  array{reference: string, amount_minor: int, currency: string, phone: string, email: string, full_name: ?string, description: string}  $payout
     * @return array{id: string, execution_url: string}
     */
    public function create(array $payout): array
    {
        try {
            $response = $this->client()->post('payouts/', [
                'brand_id' => $this->config['brand_id'],
                'reference' => $payout['reference'],
                'client' => array_filter([
                    'email' => $payout['email'],
                    'phone' => $this->msisdn($payout['phone']),
                    'country' => $this->config['country'] ?? 'UG',
                    'full_name' => $payout['full_name'],
                ]),
                'payment' => [
                    'currency' => $payout['currency'],
                    'amount' => (string) $this->toMajor($payout['amount_minor']),
                    'description' => $payout['description'],
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new PayoutRejected('Could not reach Paytota: '.$e->getMessage());
        }

        if (! $response->successful() || ! $response->json('id') || ! $response->json('execution_url')) {
            throw new PayoutRejected('Paytota did not accept the payout: '.$this->errorMessage($response->json(), $response->body()), $response->json('error.code'));
        }

        return ['id' => $response->json('id'), 'execution_url' => $this->executionUrl($response->json('id'), $response->json('execution_url'))];
    }

    /**
     * Step 2: execute the payout to Mobile Money. Returns the TUVIO status it is now in
     * ("processing" until the callback, or "paid"/"failed" if Paytota answers at once).
     * Throws PayoutRejected when Paytota refuses outright, and RuntimeException when
     * the request may or may not have gone through.
     */
    public function execute(string $executionUrl): string
    {
        try {
            $response = $this->client()->post($executionUrl, ['payout_type' => 'mobile']);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Lost contact with Paytota while sending the payout: '.$e->getMessage(), previous: $e);
        }

        $status = $response->json('status');

        if ($status === 'error' || $response->clientError()) {
            throw new PayoutRejected($this->errorMessage($response->json(), $response->body()), $response->json('error.code'));
        }

        if (! $response->successful()) {
            throw new RuntimeException("Paytota answered HTTP {$response->status()} while sending the payout: ".$response->body());
        }

        return self::OUTCOMES[$status] ?? 'processing';
    }

    /** Re-reads a payout from Paytota: "paid", "failed", "processing", or null if the lookup failed. */
    public function status(string $payoutId): ?string
    {
        try {
            $response = $this->client()->get("payouts/{$payoutId}/");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || ! is_string($response->json('status'))) {
            return null;
        }

        return $this->outcome($response->json('status'));
    }

    /** Maps a Paytota payout status (from a callback or lookup) to a TUVIO payout status. */
    public function outcome(?string $status): string
    {
        return self::OUTCOMES[$status] ?? 'processing';
    }

    /** Callbacks are RSA-SHA256 signed by the dashboard webhook's key or the company key. */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        $decoded = base64_decode((string) $signature, true);

        if (! $decoded) {
            return false;
        }

        foreach (array_filter([$this->config['webhook_public_key'] ?? null, $this->config['public_key'] ?? null]) as $publicKey) {
            if (@openssl_verify($rawBody, $decoded, $publicKey, 'sha256WithRSAEncryption') === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The API token is sent with the execute call, so it only ever goes to the Paytota
     * API host; an execution_url anywhere else is rebuilt on that host.
     */
    private function executionUrl(string $payoutId, string $executionUrl): string
    {
        $gate = parse_url($this->config['base_url']);

        if (parse_url($executionUrl, PHP_URL_HOST) === $gate['host'] && parse_url($executionUrl, PHP_URL_SCHEME) === 'https') {
            return $executionUrl;
        }

        return "{$gate['scheme']}://{$gate['host']}/po/{$payoutId}/paytota_proxy/";
    }

    private function errorMessage(mixed $json, string $body): string
    {
        return is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? $json['detail'] ?? $body) : $body;
    }

    /** Paytota amounts are whole currency units; TUVIO stores amounts ×100. */
    private function toMajor(int $amountMinor): int|float
    {
        return $amountMinor % 100 === 0 ? intdiv($amountMinor, 100) : round($amountMinor / 100, 2);
    }

    /** Paytota expects international digits without "+", e.g. 256770123456. */
    private function msisdn(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '0') ? ($this->config['dialing_code'] ?? '256').substr($digits, 1) : $digits;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->config['api_key'])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->baseUrl(rtrim($this->config['base_url'], '/').'/');
    }
}
