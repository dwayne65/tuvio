<?php

use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New plan line-up: Starter is a one-event taster (its event limit counts every event
     * ever published, not only live ones), Professional is free with a 10% fee, and
     * Enterprise is paid with a 3.7% fee. Also records the platform owner's withdrawals
     * of earned commission and plan income.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('lifetime_event_limit')->default(false)->after('event_limit');
        });

        // Bring plans that already exist in line with the seeded definitions; none are created here.
        foreach (SubscriptionPlanSeeder::definitions() as $plan) {
            DB::table('subscription_plans')->where('slug', $plan['slug'])->update([
                'description' => $plan['description'],
                'price_minor' => $plan['price_minor'],
                'commission_bps' => $plan['commission_bps'],
                'event_limit' => $plan['event_limit'],
                'lifetime_event_limit' => $plan['lifetime_event_limit'],
                'ticket_limit' => $plan['ticket_limit'],
                'team_member_limit' => $plan['team_member_limit'],
                'ussd_event_limit' => $plan['ussd_event_limit'],
                'capabilities' => json_encode($plan['capabilities']),
                'features' => json_encode($plan['features']),
            ]);
        }

        Schema::create('platform_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('method'); // mobile_money | bank
            $table->string('destination'); // phone number or bank account
            $table->string('provider_ref')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        // Adds the Owner Withdrawals ledger account; existing accounts are left as they are.
        (new ChartOfAccountsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_withdrawals');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('lifetime_event_limit');
        });
    }
};
