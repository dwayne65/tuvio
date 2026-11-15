<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each event sold on USSD gets a short code callers can dial straight into
     * (*165*24#). Codes are never reused, so a printed poster cannot end up pointing
     * at someone else's event. Every USSD session is logged for the admin funnel.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('ussd_code')->nullable()->unique()->after('sell_on_ussd');
        });

        $next = 10;
        foreach (DB::table('events')->where('sell_on_ussd', true)->orderBy('id')->pluck('id') as $id) {
            DB::table('events')->where('id', $id)->update(['ussd_code' => $next++]);
        }

        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->unique();
            $table->string('msisdn', 20)->index();
            $table->string('shortcode', 20)->nullable();
            $table->string('network', 40)->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('dialed_direct')->default(false);
            $table->string('last_step', 40)->nullable();
            $table->unsignedSmallInteger('screens')->default(0);
            $table->string('outcome', 30)->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ussd_sessions');

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['ussd_code']);
            $table->dropColumn('ussd_code');
        });
    }
};
