<?php

namespace App\Domain\Payouts;

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\LedgerLine;
use App\Domain\Ledger\LedgerService;
use App\Models\Contribution;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Computes settlement-eligible organizer earnings and processes payouts. Funds
 * become eligible only after the event has ended + the settlement window, and
 * each order/contribution can be paid out exactly once (DB-unique in payout_items).
 */
class PayoutService
{
    public function __construct(private LedgerService $ledger) {}

    private function demoMode(): bool
    {
        return ! app()->environment('production')
            && (bool) config('ticketflow.demo_mode')
            && config('ticketflow.payments.default') === 'fake';
    }

    /** Paid orders whose event settled and that are not yet in any payout. */
    public function eligibleOrders(Organization $org): Collection
    {
        $cutoff = now()->subDays(config('ticketflow.payout_settlement_days'));

        return Order::query()
            ->where('status', 'paid')
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id)
                ->when(! $this->demoMode(), fn ($q) => $q->where('start_at', '<=', $cutoff)))
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

    /** Admin marks a payout paid: posts the ledger (debit organizer payable, credit cash). */
    public function markPaid(Payout $payout, ?int $adminId = null, ?string $providerRef = null): Payout
    {
        if ($payout->status === 'paid') {
            return $payout;
        }

        return DB::transaction(function () use ($payout, $adminId, $providerRef) {
            $payout->update([
                'status' => 'paid',
                'processed_at' => now(),
                'approved_by' => $adminId,
                'provider_ref' => $providerRef,
            ]);

            $this->ledger->post('payout', [
                LedgerLine::debit($this->ledger->organizerPayable($payout->organization, $payout->currency), $payout->amount_minor, "Payout {$payout->reference}"),
                LedgerLine::credit($this->ledger->account(ChartOfAccounts::PLATFORM_CASH, $payout->currency), $payout->amount_minor, 'Cash paid out'),
            ], $payout->currency, "Payout {$payout->reference}", $payout, $adminId);

            return $payout->fresh();
        });
    }
}
