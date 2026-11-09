<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');                  // mobile_money | bank
            $table->string('provider')->nullable();  // mtn | airtel | flutterwave | bank name
            $table->string('label')->nullable();     // human label shown to organizer

            // Sensitive account details are encrypted at the application layer (model cast).
            $table->text('account_name')->nullable();
            $table->text('account_number')->nullable();
            $table->text('bank_code')->nullable();
            $table->char('currency', 3)->default('UGX');

            $table->string('verification_status')->default('unverified'); // unverified | pending | verified | rejected
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_payout_accounts');
    }
};
