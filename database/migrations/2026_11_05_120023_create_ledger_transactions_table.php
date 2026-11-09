<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type');          // ticket_sale | commission | processor_fee | payout | refund | contribution | subscription
            $table->text('description')->nullable();

            // What generated this transaction (polymorphic: Order, Payment, Payout, Contribution...).
            $table->nullableMorphs('source');

            $table->char('currency', 3)->default('UGX');
            $table->timestamp('occurred_at');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
