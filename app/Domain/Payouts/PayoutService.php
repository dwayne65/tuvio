<?php

namespace App\Domain\Payouts;

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\LedgerLine;
use App\Domain\Ledger\LedgerService;
use App\Models\Contribution;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PaymentWebhook;
use App\Models\Payout;
use App\Notifications\PayoutFailedNotification;
use App\Notifications\PayoutPaidNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Computes payout-eligible organizer earnings and processes payouts. Money is
 * eligible as soon as the buyer's payment is confirmed (no waiting for the event),
 * and each order/contribution can be paid out exactly once (DB-unique in payout_items).
 *
 * Lifecycle: requested -> processing (sent to Paytota) -> paid | failed, or
 * requested -> cancelled by an admin. A failed or cancelled payout releases its
 * items, so the money is available to request again.
 */
class PayoutService
{
    /** Payouts still waiting on an admin or on the payment provider. */
    public const OPEN_STATUSES = ['requested', 'pending', 'processing'];

    public function __construct(private LedgerService $ledger) {}

    private function gateway(): PaytotaPayouts
    {
        return new PaytotaPayouts(config('ticketflow.payments.paytota'));
    }

    /** Whether TUVIO can send this payout itself, rather than an admin paying it by hand. */
    public function canSendAutomatically(Payout $payout): bool
    {
        return config('ticketflow.payouts.driver') === 'paytota'
            && $payout->payoutAccount?->type === 'mobile_money';
    }

    private function demoMode(): bool
    {
        return ! app()->environment('production')
            && (bool) config('ticketflow.demo_mode')
            && config('ticketflow.payments.default') === 'fake';
    }

    /** Paid orders that are not yet in any payout, whenever their event takes place. */
    public function eligibleOrders(Organization $org): Collection
    {
        return Order::query()
            ->where('status', 'paid')
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
            ->whereDoesntHave('payoutItem')
            ->get();
    }

    public function eligibleContributions(Organization $org): Collection
    {
        return Contribution::query()
            ->where('status', 'successful')
            ->whereHas('campaign', fn ($q) => $q->where('organization_id', $org->id))
            ->whereDoesntHave('payoutItem')
            ->get();
    }

    public function availableMinor(Organization $org): int
    {
        $orders = $this->eligibleOrders($org)->sum(fn (Order $o) => $o->organizerNetMinor());
        $contribs = $this->eligibleContributions($org)->sum(fn (Contribution $c) => $c->amount_minor - $c->commission_minor);

        return (int) ($orders + $contribs);
    }

    /** Create a payout request covering all currently-eligible earnings. */
    public function request(Organization $org): Payout
    {
        $payout = DB::transaction(function () use ($org) {
            $orders = $this->eligibleOrders($org);
            $contribs = $this->eligibleContributions($org);

            $amount = (int) ($orders->sum(fn (Order $o) => $o->organizerNetMinor())
                + $contribs->sum(fn (Contribution $c) => $c->amount_minor - $c->commission_minor));

            if ($amount <= 0) {
                throw new RuntimeException('No funds are currently eligible for payout.');
            }

            $account = $org->payoutAccounts()->where('is_default', true)->first()
                ?? $org->payoutAccounts()->first();

            $payout = $org->payouts()->create([
                'reference' => 'PO-'.strtoupper(Str::random(8)),
                'organization_payout_account_id' => $account?->id,
                'amount_minor' => $amount,
                'currency' => $org->default_currency,
                'status' => 'requested',
                'method' => $account?->type,
                'requested_at' => now(),
            ]);

            foreach ($orders as $o) {
                $payout->items()->create(['order_id' => $o->id, 'amount_minor' => $o->organizerNetMinor(), 'currency' => $o->currency]);
            }
            foreach ($contribs as $c) {
                $payout->items()->create(['contribution_id' => $c->id, 'amount_minor' => $c->amount_minor - $c->commission_minor, 'currency' => $c->currency]);
            }

            return $payout->fresh('items');
        });

        // Demo payouts simulate the external settlement step so a local demo
        // can show the complete request -> paid lifecycle immediately.
        return $this->demoMode() ? $this->markPaid($payout) : $payout;
    }

    /**
     * Sends a just-requested payout without waiting for an admin, when it can go out
     * automatically. A refused or unconfirmed send is reported and left for the admin
     * payouts page (retry, or the callback / status check), never thrown at the organizer.
     */
    public function sendAutomatically(Payout $payout): Payout
    {
        if ($payout->status !== 'requested' || ! $this->canSendAutomatically($payout)) {
            return $payout;
        }

        try {
            return $this->send($payout);
        } catch (RuntimeException $e) {
            report($e);

            return $payout->fresh();
        }
    }

    /**
     * Settles a payout: posts the ledger (debit organizer payable, credit cash) and tells
     * the organizer. The row lock makes a callback and a status check racing each other
     * settle it once.
     */
    public function markPaid(Payout $payout, ?int $adminId = null, ?string $providerRef = null): Payout
    {
        $settled = DB::transaction(function () use ($payout, $adminId, $providerRef) {
            $locked = Payout::whereKey($payout->id)->lockForUpdate()->first();

            if (! in_array($locked->status, self::OPEN_STATUSES, true)) {
                return false;
            }

            $locked->update([
                'status' => 'paid',
                'processed_at' => now(),
                'approved_by' => $adminId ?? $locked->approved_by,
                'provider_ref' => $providerRef ?? $locked->provider_ref,
                'notes' => null,
            ]);

            $this->ledger->post('payout', [
                LedgerLine::debit($this->ledger->organizerPayable($locked->organization, $locked->currency), $locked->amount_minor, "Payout {$locked->reference}"),
                LedgerLine::credit($this->ledger->account(ChartOfAccounts::PLATFORM_CASH, $locked->currency), $locked->amount_minor, 'Cash paid out'),
            ], $locked->currency, "Payout {$locked->reference}", $locked, $adminId);

            return true;
        });

        $payout = $payout->fresh();

        if ($settled) {
            $payout->organization->owner?->notify(new PayoutPaidNotification($payout));
        }

        return $payout;
    }

    /**
     * Sends a Mobile Money payout through Paytota, either straight after the organizer's
     * request ($adminId null) or when an admin retries one. The payout is claimed with a
     * conditional update first, so a double click or two admins can never send twice.
     *
     * @throws PayoutRejected when Paytota refused it; the payout is back in "requested".
     * @throws RuntimeException when it cannot be sent, or the outcome is not yet known.
     */
    public function send(Payout $payout, ?int $adminId = null): Payout
    {
        $account = $payout->payoutAccount;

        if (! $this->canSendAutomatically($payout) || ! $account) {
            throw new RuntimeException('This payout has to be paid manually.');
        }

        $claimed = Payout::whereKey($payout->id)->where('status', 'requested')
            ->update(['status' => 'processing', 'provider' => 'paytota', 'approved_by' => $adminId, 'notes' => null]);

        if ($claimed === 0) {
            throw new RuntimeException("Payout {$payout->reference} is already being processed.");
        }

        $payout->refresh();

        $gateway = $this->gateway();
        $organization = $payout->organization;

        try {
            $created = $gateway->create([
                // Paytota wants a unique reference per attempt; a payout refused for low balance can be retried.
                'reference' => $payout->reference.'-'.now()->format('ymdHis'),
                'amount_minor' => $payout->amount_minor,
                'currency' => $payout->currency,
                'phone' => $account->account_number,
                'email' => $organization->support_email ?: $organization->owner?->email ?: 'payouts@tuvio.app',
                'full_name' => $account->account_name,
                'description' => Str::limit("TUVIO payout {$payout->reference} to {$organization->name}", 120, ''),
            ]);

            $payout->forceFill(['provider_ref' => $created['id']])->save();

            $outcome = $gateway->execute($created['execution_url']);
        } catch (PayoutRejected $e) {
            $payout->forceFill(['status' => 'requested', 'provider_ref' => null, 'notes' => $e->getMessage()])->save();

            throw $e;
        } catch (RuntimeException $e) {
            // The execute request may have reached Paytota: keep it in processing for the callback or a status check.
            $payout->forceFill(['notes' => $e->getMessage()])->save();

            throw $e;
        }

        return $this->applyOutcome($payout->fresh(), $outcome);
    }

    /** Asks Paytota for the latest status of a payout in processing. */
    public function refreshStatus(Payout $payout): Payout
    {
        if ($payout->status !== 'processing' || $payout->provider !== 'paytota' || ! $payout->provider_ref) {
            return $payout;
        }

        $outcome = $this->gateway()->status($payout->provider_ref);

        if ($outcome === null) {
            throw new RuntimeException('Could not read the payout status from Paytota. Try again shortly.');
        }

        return $this->applyOutcome($payout, $outcome);
    }

    /** Paid posts the ledger and tells the organizer; failed releases the funds. */
    public function applyOutcome(Payout $payout, string $outcome, ?string $reason = null): Payout
    {
        return match ($outcome) {
            'paid' => $this->markPaid($payout, $payout->approved_by, $payout->provider_ref),
            'failed' => $this->markFailed($payout, $reason ?? 'The Mobile Money transfer was declined.'),
            default => $payout,
        };
    }

    /**
     * Ends a payout without paying it and releases its orders and contributions, so
     * the organizer can request the money again (e.g. after fixing a wrong number).
     */
    public function markFailed(Payout $payout, string $reason, string $status = 'failed'): Payout
    {
        $released = DB::transaction(function () use ($payout, $reason, $status) {
            $locked = Payout::whereKey($payout->id)->lockForUpdate()->first();

            if (! in_array($locked->status, self::OPEN_STATUSES, true)) {
                return false;
            }

            $locked->update(['status' => $status, 'processed_at' => now(), 'notes' => $reason]);
            $locked->items()->delete();

            return true;
        });

        $payout = $payout->fresh();

        if ($released && $status === 'failed') {
            $payout->organization->owner?->notify(new PayoutFailedNotification($payout));
        }

        return $payout;
    }

    /** Payout status callback from the webhook set up in the Paytota dashboard. */
    public function handlePaytotaCallback(string $rawBody, ?string $signature, array $payload): void
    {
        $gateway = $this->gateway();

        if (! $gateway->verifySignature($rawBody, $signature)) {
            PaymentWebhook::create(['provider' => 'paytota', 'event_type' => $payload['event_type'] ?? null, 'payload' => $payload, 'status' => 'invalid', 'error' => 'Bad signature']);

            return;
        }

        $ref = $payload['id'] ?? null;
        $dedupe = 'paytota:payout:'.($ref ?? md5($rawBody)).':'.($payload['status'] ?? '');

        if (PaymentWebhook::where('dedupe_key', $dedupe)->exists()) {
            return;
        }

        $hook = PaymentWebhook::create([
            'provider' => 'paytota', 'event_type' => $payload['event_type'] ?? 'payout', 'provider_ref' => $ref,
            'signature' => $signature, 'dedupe_key' => $dedupe, 'payload' => $payload, 'status' => 'received',
        ]);

        $payout = $ref ? Payout::where('provider', 'paytota')->where('provider_ref', $ref)->first() : null;

        if (! $payout) {
            $hook->update(['status' => 'ignored', 'error' => 'No matching payout']);

            return;
        }

        // The signed callback is authoritative; a live lookup only overrides it when it is final.
        $outcome = $gateway->outcome($payload['status'] ?? null);
        $outcome = $outcome === 'processing' ? ($gateway->status($ref) ?? $outcome) : $outcome;

        $reason = $payload['transaction_data']['attempts'][0]['error']['message'] ?? $payload['error']['message'] ?? null;
        $this->applyOutcome($payout, $outcome, $reason);

        $hook->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
