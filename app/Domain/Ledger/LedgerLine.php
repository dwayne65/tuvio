<?php

namespace App\Domain\Ledger;

use App\Models\LedgerAccount;

/** One leg of a double-entry posting. */
final class LedgerLine
{
    private function __construct(
        public readonly LedgerAccount $account,
        public readonly string $direction,   // debit | credit
        public readonly int $amountMinor,
        public readonly ?string $memo = null,
    ) {}

    public static function debit(LedgerAccount $account, int $amountMinor, ?string $memo = null): self
    {
        return new self($account, 'debit', $amountMinor, $memo);
    }

    public static function credit(LedgerAccount $account, int $amountMinor, ?string $memo = null): self
    {
        return new self($account, 'credit', $amountMinor, $memo);
    }
}
