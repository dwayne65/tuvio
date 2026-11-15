<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paytota signs callbacks with RSA, and the base64 signature (344+ characters) does not
     * fit a 255-character column. Postgres rejected those inserts, so every signed callback
     * failed with a 500 and its payment was never confirmed.
     */
    public function up(): void
    {
        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->text('signature')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->string('signature')->nullable()->change();
        });
    }
};
