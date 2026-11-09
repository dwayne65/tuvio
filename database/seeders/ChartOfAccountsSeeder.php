<?php

namespace Database\Seeders;

use App\Domain\Ledger\ChartOfAccounts;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $currency = config('ticketflow.default_currency', 'UGX');

        foreach (ChartOfAccounts::platformAccounts() as $code => [$name, $type, $normal]) {
            LedgerAccount::updateOrCreate(
                ['code' => $code, 'owner_type' => null, 'owner_id' => null, 'currency' => $currency],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normal, 'is_active' => true],
            );
        }
    }
}
