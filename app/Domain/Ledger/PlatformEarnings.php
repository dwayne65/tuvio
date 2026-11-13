<?php

namespace App\Domain\Ledger;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PlatformWithdrawal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * What the platform itself has earned, straight from the ledger: commission on ticket
 * sales, booking fees paid by buyers and plan subscription payments, less payment
 * processor charges and refunds. Tax collected on fees is shown but never counts as
 * earnings, and organizers' money is never part of it. Withdrawals take earnings out.
 */
class PlatformEarnings
{
    public function __construct(private LedgerService $ledger) {}

    /**
     * @return array{commission: int, service_fees: int, subscriptions: int, processor_fees: int, refunds: int,
     *     earned: int, withdrawn: int, available: int, tax_held: int}
     */
    public function summary(string $currency): array
    {
        $commission = $this->balance(ChartOfAccounts::COMMISSION_INCOME, $currency);
        $serviceFees = $this->balance(ChartOfAccounts::SERVICE_FEE_INCOME, $currency);
        $subscriptions = $this->balance(ChartOfAccounts::SUBSCRIPTION_INCOME, $currency);
        $processorFees = $this->balance(ChartOfAccounts::PROCESSOR_FEE_EXPENSE, $currency);
        $refunds = $this->balance(ChartOfAccounts::REFUNDS_CLEARING, $currency);
        $withdrawn = $this->balance(ChartOfAccounts::OWNER_WITHDRAWALS, $currency);
        $earned = $commission + $serviceFees + $subscriptions - $processorFees - $refunds;

        return [
            'commission' => $commission,
            'service_fees' => $serviceFees,
            'subscriptions' => $subscriptions,
            'processor_fees' => $processorFees,
            'refunds' => $refunds,
            'earned' => $earned,
            'withdrawn' => $withdrawn,
            'available' => max(0, $earned - $withdrawn),
            'tax_held' => $this->balance(ChartOfAccounts::TAX_PAYABLE, $currency),
        ];
    }

    /**
     * Income per month, oldest first, zero-filled.
     *
     * @return list<array{month: string, commission: int, service_fees: int, subscriptions: int}>
     */
    public function monthly(string $currency, int $months = 6): array
    {
        $codes = [
            ChartOfAccounts::COMMISSION_INCOME => 'commission',
            ChartOfAccounts::SERVICE_FEE_INCOME => 'service_fees',
            ChartOfAccounts::SUBSCRIPTION_INCOME => 'subscriptions',
        ];
        $accounts = LedgerAccount::whereNull('owner_type')->where('currency', $currency)
            ->whereIn('code', array_keys($codes))->pluck('code', 'id');
        $from = now()->startOfMonth()->subMonths($months - 1);

        $rows = collect(range(0, $months - 1))->mapWithKeys(fn (int $i) => [
            $from->copy()->addMonths($i)->format('Y-m') => ['month' => $from->copy()->addMonths($i)->format('Y-m'), 'commission' => 0, 'service_fees' => 0, 'subscriptions' => 0],
        ])->all();

        LedgerEntry::whereIn('ledger_account_id', $accounts->keys())
            ->where('created_at', '>=', $from)
            ->get(['ledger_account_id', 'direction', 'amount_minor', 'created_at'])
            ->each(function (LedgerEntry $entry) use (&$rows, $accounts, $codes) {
                $month = Carbon::parse($entry->created_at)->format('Y-m');
                if (isset($rows[$month])) {
                    $rows[$month][$codes[$accounts[$entry->ledger_account_id]]] += $entry->direction === 'credit' ? $entry->amount_minor : -$entry->amount_minor;
                }
            });

        return array_values($rows);
    }

    /**
     * Take earnings out of the platform: records the withdrawal and posts it to the ledger
     * (debit owner withdrawals, credit cash). Refuses more than is available, so organizer
     * balances and tax held for the tax authority can never be withdrawn.
     *
     * @param  array{amount_minor: int, method: string, destination: string, provider_ref?: ?string, note?: ?string}  $data
     */
    public function withdraw(User $admin, string $currency, array $data): PlatformWithdrawal
    {
        return DB::transaction(function () use ($admin, $currency, $data) {
            // Serialise withdrawals so two at once cannot both spend the same balance.
            $withdrawals = $this->ledger->account(ChartOfAccounts::OWNER_WITHDRAWALS, $currency);
            LedgerAccount::whereKey($withdrawals->id)->lockForUpdate()->first();

            $available = $this->summary($currency)['available'];
            if ($data['amount_minor'] <= 0 || $data['amount_minor'] > $available) {
                throw new RuntimeException('You can withdraw at most '.number_format($available / 100).' '.$currency.' right now.');
            }

            $withdrawal = PlatformWithdrawal::create([
                'reference' => 'WD-'.strtoupper(Str::random(10)),
                'amount_minor' => $data['amount_minor'],
                'currency' => $currency,
                'method' => $data['method'],
                'destination' => $data['destination'],
                'provider_ref' => $data['provider_ref'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $admin->id,
                'paid_at' => now(),
            ]);

            $this->ledger->post('owner_withdrawal', [
                LedgerLine::debit($this->ledger->account(ChartOfAccounts::OWNER_WITHDRAWALS, $currency), $withdrawal->amount_minor, "Withdrawal {$withdrawal->reference}"),
                LedgerLine::credit($this->ledger->account(ChartOfAccounts::PLATFORM_CASH, $currency), $withdrawal->amount_minor, 'Earnings withdrawn'),
            ], $currency, "Platform withdrawal {$withdrawal->reference}", $withdrawal, $admin->id);

            return $withdrawal;
        });
    }

    private function balance(string $code, string $currency): int
    {
        return LedgerAccount::whereNull('owner_type')->where('code', $code)->where('currency', $currency)->first()?->balanceMinor() ?? 0;
    }
}
