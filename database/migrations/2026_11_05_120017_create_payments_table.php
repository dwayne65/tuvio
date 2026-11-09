<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // A payment settles exactly one of these (enforced in the app layer).
            $table->string('purpose');   // ticket_order | subscription | contribution
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider');          // flutterwave | pesapal | ...
            $table->string('provider_ref')->nullable(); // provider transaction id / tx_ref
            $table->string('method')->nullable();       // mobile_money | card
            $table->string('idempotency_key')->unique();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('processor_fee_minor')->default(0);

            $table->string('status')->default('initiated');
            // initiated | pending | successful | failed | refunded | chargeback

            $table->json('provider_payload')->nullable(); // last known provider response (redacted)
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('verified_at')->nullable();  // set ONLY after server-side verification
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_ref']);
            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
