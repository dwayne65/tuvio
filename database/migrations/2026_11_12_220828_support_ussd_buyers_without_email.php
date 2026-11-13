<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** USSD buyers are identified by phone number and often have no email address. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('buyer_email')->nullable()->change();
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->string('contributor_phone')->nullable()->after('contributor_email');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('contributor_phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('buyer_email')->nullable(false)->change();
        });
    }
};
