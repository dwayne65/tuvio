<?php

namespace Tests\Feature;

use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\LedgerLine;
use App\Domain\Ledger\LedgerService;
use App\Models\LedgerTransaction;
use App\Models\PlatformWithdrawal;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);

        // 50,000.00 commission and 20,000.00 plan income collected, 10,000.00 tax held, 2,000.00 processor charge.
        $ledger = app(LedgerService::class);
        $ledger->post('ticket_sale', [
            LedgerLine::debit($ledger->account(ChartOfAccounts::PLATFORM_CASH), 7800000),
            LedgerLine::debit($ledger->account(ChartOfAccounts::PROCESSOR_FEE_EXPENSE), 200000),
            LedgerLine::credit($ledger->account(ChartOfAccounts::COMMISSION_INCOME), 5000000),
            LedgerLine::credit($ledger->account(ChartOfAccounts::SUBSCRIPTION_INCOME), 2000000),
            LedgerLine::credit($ledger->account(ChartOfAccounts::TAX_PAYABLE), 1000000),
        ]);
    }

    private function withdraw(User $admin, array $overrides = [])
    {
        return $this->actingAs($admin)->post('/admin/earnings/withdraw', [
            'amount' => '30000', 'method' => 'mobile_money', 'destination' => '0770123456', 'password' => 'password', ...$overrides,
        ]);
    }

    public function test_admin_sees_earnings_without_the_tax_held(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/earnings')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Earnings', false)
            ->where('summary.commission', 5000000)
            ->where('summary.subscriptions', 2000000)
            ->where('summary.tax_held', 1000000)
            ->where('summary.available', 6800000));
    }

    public function test_withdrawal_needs_the_password_and_cannot_exceed_earnings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withdraw($admin, ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->withdraw($admin, ['amount' => '68001'])->assertSessionHasErrors('amount');
        $this->assertSame(0, PlatformWithdrawal::count());
    }

    public function test_withdrawal_is_recorded_in_the_ledger_and_reduces_what_is_available(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withdraw($admin)->assertSessionHas('success');

        $withdrawal = PlatformWithdrawal::sole();
        $this->assertSame(3000000, $withdrawal->amount_minor);
        $this->assertTrue(LedgerTransaction::where('type', 'owner_withdrawal')->sole()->isBalanced());

        $this->actingAs($admin)->get('/admin/earnings')->assertInertia(fn ($page) => $page
            ->where('summary.withdrawn', 3000000)
            ->where('summary.available', 3800000));
    }

    public function test_only_admins_reach_earnings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/earnings')->assertForbidden();
        $this->withdraw($user)->assertForbidden();
    }
}
