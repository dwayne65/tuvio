<?php

namespace App\Domain\Ledger;

/**
 * Canonical account codes for the platform ledger. Subsidiary accounts (e.g. a
 * payable balance per organizer) are created on demand, keyed by owner, sharing
 * the ORGANIZER_PAYABLE code semantics but scoped via owner_type/owner_id.
 */
final class ChartOfAccounts
{
    // Platform-level accounts
    public const PLATFORM_CASH = 'PLATFORM_CASH';           // asset  (debit)

    public const COMMISSION_INCOME = 'COMMISSION_INCOME';   // income (credit)

    public const SERVICE_FEE_INCOME = 'SERVICE_FEE_INCOME'; // income (credit)

    public const SUBSCRIPTION_INCOME = 'SUBSCRIPTION_INCOME'; // income (credit)

    public const PROCESSOR_FEE_EXPENSE = 'PROCESSOR_FEE_EXPENSE'; // expense (debit)

    public const TAX_PAYABLE = 'TAX_PAYABLE';               // liability (credit)

    public const REFUNDS_CLEARING = 'REFUNDS_CLEARING';     // contra/expense (debit)

    // Per-organizer subsidiary account (money the platform owes organizers)
    public const ORGANIZER_PAYABLE = 'ORGANIZER_PAYABLE';   // liability (credit)

    /** Seed definitions: [code => [name, type, normal_balance]]. */
    public static function platformAccounts(): array
    {
        return [
            self::PLATFORM_CASH => ['Platform Cash', 'asset', 'debit'],
            self::COMMISSION_INCOME => ['Commission Income', 'income', 'credit'],
            self::SERVICE_FEE_INCOME => ['Service Fee Income', 'income', 'credit'],
            self::SUBSCRIPTION_INCOME => ['Subscription Income', 'income', 'credit'],
            self::PROCESSOR_FEE_EXPENSE => ['Payment Processor Fees', 'expense', 'debit'],
            self::TAX_PAYABLE => ['Tax Payable', 'liability', 'credit'],
            self::REFUNDS_CLEARING => ['Refunds Clearing', 'expense', 'debit'],
        ];
    }
}
