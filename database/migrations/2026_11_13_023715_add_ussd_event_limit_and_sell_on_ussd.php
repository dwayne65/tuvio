<?php

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * USSD sales are a plan feature: a plan allows a number of upcoming events on the
     * USSD menu at a time (0 = none, null = unlimited), and organizers choose which
     * events by switching sell_on_ussd on.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('ussd_event_limit')->nullable()->default(0)->after('team_member_limit');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('sell_on_ussd')->default(false)->after('visibility');
        });

        // Bring plans that already exist in line with the seeded definitions; none are created here.
        foreach (SubscriptionPlanSeeder::definitions() as $plan) {
            DB::table('subscription_plans')->where('slug', $plan['slug'])->update([
                'ussd_event_limit' => $plan['ussd_event_limit'],
                'features' => json_encode($plan['features']),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('ussd_event_limit');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('sell_on_ussd');
        });
    }
};
