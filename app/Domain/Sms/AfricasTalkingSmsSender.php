<?php

namespace App\Domain\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Africa's Talking bulk SMS API (api.africastalking.com, or the sandbox). */
class AfricasTalkingSmsSender implements SmsSender
{
    /** Recipient status codes meaning the message was accepted: Processed, Sent, Queued. */
    private const ACCEPTED = [100, 101, 102];

    public function __construct(private array $config) {}

    public function send(string $to, string $message): SmsResult
    {
        $url = $this->config['sandbox']
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        try {
            $response = Http::withHeaders(['apiKey' => $this->config['api_key']])
                ->acceptJson()
                ->asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post($url, array_filter([
                    'username' => $this->config['username'],
                    'to' => $to,
                    'message' => $message,
                    // A registered sender ID such as "TUVIO"; without one the default shortcode is used.
                    'from' => $this->config['sender_id'] ?: null,
                ]));
        } catch (ConnectionException $e) {
            return new SmsResult(sent: false, error: $e->getMessage());
        }

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if (! $response->successful() || ! in_array($recipient['statusCode'] ?? null, self::ACCEPTED, true)) {
            return new SmsResult(sent: false, error: $recipient['status'] ?? $response->json('SMSMessageData.Message') ?? $response->body());
        }

        return new SmsResult(sent: true, messageId: $recipient['messageId'] ?? null);
    }
}
