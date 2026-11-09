<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_payout_account_id')->nullable()->constrained()->nullOnDelete();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('status')->default('requested');
            // requested | pending | processing | paid | failed | cancelled

            $table->string('method')->nullable();      // mobile_money | bank
            $table->string('provider')->nullable();
            $table->string('provider_ref')->nullable();

            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
