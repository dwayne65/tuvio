<?php

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each plan sets the platform commission on its organizers' ticket sales and the
     * dashboard features it unlocks. Paid plans no longer start with a free trial.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('commission_bps')->nullable()->after('currency');
            $table->json('capabilities')->nullable()->after('features');
        });

        // Bring plans that already exist in line with the seeded definitions; none are created here.
        foreach (SubscriptionPlanSeeder::definitions() as $plan) {
            DB::table('subscription_plans')->where('slug', $plan['slug'])->update([
                'commission_bps' => $plan['commission_bps'],
                'capabilities' => json_encode($plan['capabilities']),
                'features' => json_encode($plan['features']),
                'description' => $plan['description'],
                'ticket_limit' => $plan['ticket_limit'],
                'trial_days' => 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['commission_bps', 'capabilities']);
        });
    }
};
