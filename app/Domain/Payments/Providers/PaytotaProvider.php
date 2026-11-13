<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\MobileMoneyPromptFailed;
use App\Domain\Payments\PaymentIntent;
use App\Domain\Payments\VerificationResult;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paytota adapter (gate.paytota.com). Buyers complete Mobile Money or card via the
 * hosted checkout; verification re-reads the purchase server-side. Mirrors the
 * official paytota-sdk-php endpoints without its Guzzle 7 dependency.
 */
class PaytotaProvider implements PaymentProvider
{
    /** Paytota's name for Mobile Money (MTN / Airtel) collected by a PIN prompt on the phone. */
    public const MOBILE_MONEY = 'paytota_proxy';

    public const CARD_METHODS = ['visa', 'mastercard', 'maestro', 'american_express', 'discover'];

    /** Purchase statuses that will never become paid; anything else unpaid is still in progress. */
    private const FAILED_STATUSES = ['error', 'cancelled', 'expired', 'blocked'];

    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'paytota';
    }

    public function initiate(Payment $payment, string $returnUrl): PaymentIntent
    {
        try {
            $response = $this->client()->post('purchases/', array_filter([
                'brand_id' => $this->config['brand_id'],
                'reference' => $payment->reference,
                'client' => array_filter([
                    'email' => $payment->order?->buyer_email ?? $payment->contribution?->contributor_email
                        ?? $payment->subscriptionInvoice?->subscription?->organization?->support_email ?? 'customer@tuvio.app',
                    'full_name' => $payment->order?->buyer_name ?? $payment->contribution?->contributor_name
                        ?? $payment->subscriptionInvoice?->subscription?->organization?->name ?? 'Customer',
                    'phone' => $this->msisdn($payment->order?->buyer_phone ?? $payment->contribution?->contributor_phone ?? $payment->subscriptionInvoice?->payer_phone),
                    'country' => $this->config['country'] ?? 'UG',
                ]),
                'purchase' => [
                    'currency' => $payment->currency,
                    // A single line priced at the exact payable total, so Paytota's total always
                    // matches what verifyAndFulfill() cross-checks (fees and discounts included).
                    'products' => [
                        ['name' => $this->describe($payment), 'price' => $this->toMajor($payment->amount_minor), 'quantity' => 1],
                    ],
                ],
                'success_redirect' => $returnUrl,
                'failure_redirect' => $returnUrl,
                'cancel_redirect' => $returnUrl,
                'success_callback' => $this->callbackUrl(),
                'payment_method_whitelist' => match ($payment->method) {
                    'mobile_money' => [self::MOBILE_MONEY],
                    'card' => self::CARD_METHODS,
                    default => null,
                },
            ]));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to initiate Paytota payment: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || ! $response->json('checkout_url')) {
            throw new RuntimeException('Failed to initiate Paytota payment: '.$response->body());
        }

        if ($payment->method === 'mobile_money') {
            return $this->sendPhonePrompt($response->json('id'));
        }

        return new PaymentIntent(
            providerRef: $response->json('id'),
            redirectUrl: $response->json('checkout_url'),
        );
    }

    /** Server-to-server execute: Paytota pushes a PIN prompt to the buyer's phone. */
    private function sendPhonePrompt(string $purchaseId): PaymentIntent
    {
        // The execute goes to /p/{id}/?s2s=true on the API host (gate.paytota.com). checkout_url
        // may point at another host (payments.paytota.com) that rejects the API key, and without
        // s2s in the query string Paytota just renders its payment page and sends no prompt.
        $gate = parse_url($this->config['base_url']);
        $executeUrl = "{$gate['scheme']}://{$gate['host']}/p/{$purchaseId}/?s2s=true";

        // An accepted prompt answers {"status": "pending"} or redirects to /p/{id}/pending/.
        // Anything else, typically a redirect to failure_redirect after Paytota fell back to the
        // card form, means no prompt was sent. Redirects are never followed: failure_redirect
        // points back at this app and would deadlock a single-worker server.
        try {
            $response = $this->client()->withoutRedirecting()->asForm()->post($executeUrl, ['s2s' => 'true', 'pm' => self::MOBILE_MONEY]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to send the Mobile Money prompt: '.$e->getMessage(), previous: $e);
        }

        $accepted = ($response->successful() && $response->json('status') === 'pending')
            || ($response->redirect() && str_ends_with(rtrim((string) parse_url((string) $response->header('Location'), PHP_URL_PATH), '/'), "/p/{$purchaseId}/pending"));

        if (! $accepted) {
            throw new MobileMoneyPromptFailed($purchaseId, "Paytota did not send the Mobile Money prompt (HTTP {$response->status()}): ".$response->body());
        }

        return new PaymentIntent(
            providerRef: $purchaseId,
            meta: ['awaiting_phone_approval' => true],
        );
    }

    public function verify(string $providerRef): VerificationResult
    {
        try {
            $response = $this->client()->get("purchases/{$providerRef}/");
        } catch (ConnectionException) {
            $response = null;
        }

        $data = $response?->json() ?? [];

        $status = $data['status'] ?? null;
        $paid = $response?->successful() === true && $status === 'paid';
        $method = $data['transaction_data']['payment_method'] ?? null;

        return new VerificationResult(
            successful: $paid,
            amountMinor: $this->toMinor($data['purchase']['total'] ?? 0),
            currency: $data['purchase']['currency'] ?? 'UGX',
            processorFeeMinor: $this->toMinor($data['payment']['fee_amount'] ?? 0),
            method: match (true) {
                $method === self::MOBILE_MONEY => 'mobile_money',
                in_array($method, self::CARD_METHODS, true) => 'card',
                default => $method ?: null,
            },
            raw: $data,
            // A failed lookup (network, 5xx) is not proof the buyer failed to pay.
            pending: ! $paid && ! in_array($status, self::FAILED_STATUSES, true),
        );
    }

    /** Callbacks are RSA-SHA256 (PKCS#1 v1.5) signed; the base64 signature arrives in X-Signature or Signature. */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $publicKey = $this->config['public_key'] ?? null;

        if (! $publicKey || ! $signature) {
            return false;
        }

        return openssl_verify($rawBody, (string) base64_decode($signature, true), $publicKey, 'sha256WithRSAEncryption') === 1;
    }

    public function webhookReference(array $payload): ?string
    {
        return $payload['id'] ?? null;
    }

    /**
     * Paytota prices are in whole currency units (150000 UGX is shown as USh150,000.00),
     * while TUVIO stores amounts ×100 in *_minor columns.
     */
    private function toMajor(int $amountMinor): int|float
    {
        return $amountMinor % 100 === 0 ? intdiv($amountMinor, 100) : round($amountMinor / 100, 2);
    }

    private function toMinor(int|float|string $amountMajor): int
    {
        return (int) round(((float) $amountMajor) * 100);
    }

    /** Paytota expects international digits without "+", e.g. 256770123456. */
    private function msisdn(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? ($this->config['dialing_code'] ?? '256').substr($digits, 1) : $digits;
    }

    /**
     * Built from APP_URL rather than the current request, so browsing on 127.0.0.1:8000
     * still hands Paytota a public address (e.g. a tunnel). Paytota only accepts callbacks
     * on the default HTTP(S) ports, so a URL such as localhost:8000 is skipped; the
     * dashboard webhook and the approval page still verify.
     */
    private function callbackUrl(): ?string
    {
        $url = rtrim((string) config('app.url'), '/').route('webhooks.paytota', absolute: false);

        return parse_url($url, PHP_URL_PORT) === null ? $url : null;
    }

    /** What the buyer sees on Paytota's checkout, e.g. "Summit — 2 × Regular, 1 × VIP". */
    private function describe(Payment $payment): string
    {
        if ($order = $payment->order) {
            $items = $order->items->map(fn ($item) => "{$item->quantity} × {$item->description}")->implode(', ');

            return Str::limit($items === '' ? $order->event->title : "{$order->event->title} — {$items}", 250);
        }

        if ($campaign = $payment->contribution?->campaign) {
            return Str::limit("Contribution to {$campaign->title}", 250);
        }

        if ($plan = $payment->subscriptionInvoice?->subscription?->plan) {
            return Str::limit("TUVIO {$plan->name} plan, invoice {$payment->subscriptionInvoice->number}", 250);
        }

        return "Payment {$payment->reference}";
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->config['api_key'])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->baseUrl(rtrim($this->config['base_url'], '/').'/');
    }
}
