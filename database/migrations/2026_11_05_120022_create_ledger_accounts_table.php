<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // e.g. PLATFORM_CASH, COMMISSION_INCOME
            $table->string('name');
            $table->string('type');                  // asset | liability | income | expense | equity
            $table->string('normal_balance');        // debit | credit

            // Subsidiary ledgers (e.g. one payable account per organization).
            $table->nullableMorphs('owner');         // owner_type + owner_id
            $table->char('currency', 3)->default('UGX');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
