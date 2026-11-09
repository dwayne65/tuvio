<?php

namespace App\Domain\Ledger;

use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Posts balanced double-entry transactions. Every posting is atomic and must
 * balance (sum of debits === sum of credits) or it is rejected before anything
 * is written. Entries are append-only (see the DB trigger on ledger_entries),
 * so posted history can never be silently altered.
 */
class LedgerService
{
    /**
     * @param  LedgerLine[]  $lines
     */
    public function post(
        string $type,
        array $lines,
        string $currency = 'UGX',
        ?string $description = null,
        ?Model $source = null,
        ?int $postedBy = null,
    ): LedgerTransaction {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A ledger transaction needs at least two lines.');
        }

        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            if ($line->amountMinor <= 0) {
                throw new InvalidArgumentException('Ledger line amounts must be positive minor units.');
            }
            $line->direction === 'debit'
                ? $debits += $line->amountMinor
                : $credits += $line->amountMinor;
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException(
                "Unbalanced ledger posting: debits {$debits} !== credits {$credits}."
            );
        }

        return DB::transaction(function () use ($type, $lines, $currency, $description, $source, $postedBy) {
            $transaction = LedgerTransaction::create([
                'reference' => 'LT-'.strtoupper(Str::random(12)),
                'type' => $type,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'currency' => $currency,
                'occurred_at' => now(),
                'posted_by' => $postedBy,
            ]);

            foreach ($lines as $line) {
                $transaction->entries()->create([
                    'ledger_account_id' => $line->account->id,
                    'direction' => $line->direction,
                    'amount_minor' => $line->amountMinor,
                    'currency' => $currency,
                    'memo' => $line->memo,
                ]);
            }

            return $transaction;
        });
    }

    /** Resolve a platform-level account by code (must be seeded). */
    public function account(string $code, string $currency = 'UGX'): LedgerAccount
    {
        return LedgerAccount::query()
            ->whereNull('owner_type')
            ->where('code', $code)
            ->where('currency', $currency)
            ->firstOrFail();
    }

    /** Resolve (or create) the subsidiary payable account for an organizer. */
    public function organizerPayable(Model $organization, string $currency = 'UGX'): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            [
                'code' => ChartOfAccounts::ORGANIZER_PAYABLE,
                'owner_type' => $organization->getMorphClass(),
                'owner_id' => $organization->getKey(),
                'currency' => $currency,
            ],
            [
                'name' => "Organizer Payable #{$organization->getKey()}",
                'type' => 'liability',
                'normal_balance' => 'credit',
                'is_active' => true,
            ],
        );
    }
}
