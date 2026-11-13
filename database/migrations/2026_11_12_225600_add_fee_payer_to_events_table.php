<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Who covers the platform fee: the organizer (taken from earnings) or the buyer (added at checkout).
            $table->string('fee_payer', 16)->default('organizer')->after('commission_bps');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('fee_payer');
        });
    }
};
