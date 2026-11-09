<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\LedgerLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Tickets\TicketIssuer;
use App\Models\Contribution;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private PaymentManager $manager,
        private LedgerService $ledger,
        private TicketIssuer $issuer,
    ) {}

    public function initiateForOrder(Order $order, string $returnUrl, ?string $providerName = null): PaymentIntent
    {
        if ($order->total_minor <= 0) {
            throw new RuntimeException('Free orders do not require payment.');
        }

        return $this->initiate([
            'purpose' => 'ticket_order',
            'order_id' => $order->id,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'existing' => $order->payments()->whereIn('status', ['initiated', 'pending'])->first(),
        ], $returnUrl, $providerName);
    }

    public function initiateForContribution(Contribution $contribution, string $returnUrl, ?string $providerName = null): PaymentIntent
    {
        return $this->initiate([
            'purpose' => 'contribution',
            'contribution_id' => $contribution->id,
            'amount_minor' => $contribution->amount_minor,
            'currency' => $contribution->currency,
            'existing' => Payment::where('contribution_id', $contribution->id)->whereIn('status', ['initiated', 'pending'])->first(),
        ], $returnUrl, $providerName);
    }

    private function initiate(array $spec, string $returnUrl, ?string $providerName): PaymentIntent
    {
        $provider = $this->manager->provider($providerName);

        $payment = $spec['existing'] ?? Payment::create([
            'reference' => 'PAY-'.strtoupper(Str::random(10)),
            'purpose' => $spec['purpose'],
            'order_id' => $spec['order_id'] ?? null,
            'contribution_id' => $spec['contribution_id'] ?? null,
            'subscription_invoice_id' => $spec['subscription_invoice_id'] ?? null,
            'provider' => $provider->name(),
            'idempotency_key' => (string) Str::uuid(),
            'amount_minor' => $spec['amount_minor'],
            'currency' => $spec['currency'],
            'status' => 'initiated',
        ]);

        $intent = $provider->initiate($payment, $returnUrl);
        $payment->update(['provider_ref' => $intent->providerRef, 'status' => 'pending']);

        return $intent;
    }

    /** Verify server-side and fulfil the payment's purpose. Idempotent. */
    public function verifyAndFulfill(Payment $payment): bool
    {
        if ($payment->isVerified()) {
            $this->dispatchFulfilment($payment);

            return true;
        }

        $provider = $this->manager->provider($payment->provider);
        $result = $provider->verify($payment->provider_ref);

        if (! $result->successful) {
            $payment->update(['status' => 'failed', 'failed_at' => now(), 'provider_payload' => $result->raw]);

            return false;
        }

        if ($result->amountMinor > 0 && $result->amountMinor !== $payment->amount_minor) {
            $payment->update(['status' => 'failed', 'failed_at' => now(), 'provider_payload' => $result->raw]);
            throw new RuntimeException('Payment amount mismatch; refusing to fulfil.');
        }

        DB::transaction(function () use ($payment, $result) {
            $payment->update([
                'status' => 'successful',
                'verified_at' => now(),
                'processor_fee_minor' => $result->processorFeeMinor,
                'method' => $result->method,
                'provider_payload' => $result->raw,
            ]);

            $this->dispatchFulfilment($payment);
        });

        return true;
    }

    private function dispatchFulfilment(Payment $payment): void
    {
        match ($payment->purpose) {
            'ticket_order' => $this->fulfillOrder($payment),
            'contribution' => $this->fulfillContribution($payment),
            default => null,
        };
    }

    private function fulfillOrder(Payment $payment): void
    {
        $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
        if ($order->status !== 'paid') {
            $order->update(['status' => 'paid', 'paid_at' => now()]);
        }

        $this->issuer->issueForOrder($order);

        if ($this->alreadyPosted($order, 'ticket_sale')) {
            return;
        }

        $currency = $order->currency;
        $processorFee = $payment->processor_fee_minor ?? 0;
        $this->postSale('ticket_sale', $order, $order->event->organization, $currency, [
            'gross' => $order->total_minor,
            'processorFee' => $processorFee,
            'commission' => $order->commission_minor,
            'serviceFee' => $order->fee_minor,
            'organizerNet' => $order->organizerNetMinor(),
        ], "Ticket sale {$order->reference}");
    }

    private function fulfillContribution(Payment $payment): void
    {
        $contribution = Contribution::whereKey($payment->contribution_id)->lockForUpdate()->firstOrFail();
        if ($contribution->status === 'successful') {
            return;
        }

        $contribution->update([
            'status' => 'successful',
            'completed_at' => now(),
            'receipt_number' => $contribution->receipt_number ?? 'RCP-'.strtoupper(Str::random(10)),
        ]);

        $campaign = $contribution->campaign;
        $campaign->increment('raised_minor', $contribution->amount_minor);
        $campaign->increment('contributors_count');

        if ($this->alreadyPosted($contribution, 'contribution')) {
            return;
        }

        $this->postSale('contribution', $contribution, $campaign->organization, $contribution->currency, [
            'gross' => $contribution->amount_minor,
            'processorFee' => $payment->processor_fee_minor ?? 0,
            'commission' => $contribution->commission_minor,
            'serviceFee' => 0,
            'organizerNet' => $contribution->amount_minor - $contribution->commission_minor,
        ], "Contribution {$contribution->reference}");
    }

    /** Shared balanced posting for a collected sale/contribution. */
    private function postSale(string $type, $source, $organization, string $currency, array $amounts, string $desc): void
    {
        $cash = $amounts['gross'] - $amounts['processorFee'];
        $lines = [LedgerLine::debit($this->ledger->account(ChartOfAccounts::PLATFORM_CASH, $currency), $cash, $desc)];

        if ($amounts['processorFee'] > 0) {
            $lines[] = LedgerLine::debit($this->ledger->account(ChartOfAccounts::PROCESSOR_FEE_EXPENSE, $currency), $amounts['processorFee'], 'Processor fee');
        }
        if ($amounts['commission'] > 0) {
            $lines[] = LedgerLine::credit($this->ledger->account(ChartOfAccounts::COMMISSION_INCOME, $currency), $amounts['commission'], 'Platform commission');
        }
        if ($amounts['serviceFee'] > 0) {
            $lines[] = LedgerLine::credit($this->ledger->account(ChartOfAccounts::SERVICE_FEE_INCOME, $currency), $amounts['serviceFee'], 'Service fee');
        }
        if ($amounts['organizerNet'] > 0) {
            $lines[] = LedgerLine::credit($this->ledger->organizerPayable($organization, $currency), $amounts['organizerNet'], 'Organizer net');
        }

        $this->ledger->post($type, $lines, $currency, $desc, $source);
    }

    private function alreadyPosted($source, string $type): bool
    {
        return LedgerTransaction::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('type', $type)
            ->exists();
    }

    public function fulfillFree(Order $order): void
    {
        if ($order->total_minor > 0) {
            throw new RuntimeException('Order is not free.');
        }

        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'paid') {
                $order->update(['status' => 'paid', 'paid_at' => now()]);
            }
            $this->issuer->issueForOrder($order);
        });
    }

    public function handleWebhook(string $providerName, string $rawBody, ?string $signature, array $payload): void
    {
        $provider = $this->manager->provider($providerName);

        if (! $provider->verifyWebhookSignature($rawBody, $signature)) {
            PaymentWebhook::create(['provider' => $providerName, 'payload' => $payload, 'status' => 'invalid', 'error' => 'Bad signature']);

            return;
        }

        $ref = $provider->webhookReference($payload);
        $dedupe = $providerName.':'.($ref ?? md5($rawBody));

        if (PaymentWebhook::where('dedupe_key', $dedupe)->exists()) {
            return;
        }

        $hook = PaymentWebhook::create([
            'provider' => $providerName, 'event_type' => $payload['event'] ?? null,
            'provider_ref' => $ref, 'signature' => $signature, 'dedupe_key' => $dedupe,
            'payload' => $payload, 'status' => 'received',
        ]);

        $payment = Payment::where('provider', $providerName)->where('provider_ref', $ref)->first();
        if (! $payment) {
            $hook->update(['status' => 'ignored', 'error' => 'No matching payment']);

            return;
        }

        $this->verifyAndFulfill($payment);
        $hook->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
