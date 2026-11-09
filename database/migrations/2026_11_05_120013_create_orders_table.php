<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();         // human order reference (e.g. TF-7F3K9Q)
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = guest
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();

            // Buyer (captured even for guest checkout)
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();

            $table->string('status')->default('pending');
            // pending | awaiting_payment | paid | failed | cancelled | expired | refunded | partially_refunded

            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('fee_minor')->default(0);          // buyer-facing platform/service fee
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);        // what the buyer pays
            $table->bigInteger('commission_minor')->default(0);   // platform commission (from organizer share)

            // Idempotency + inventory reservation
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('reserved_until')->nullable();      // hold expiry for inventory
            $table->timestamp('paid_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'reserved_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
