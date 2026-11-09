<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('contributor_name')->nullable();
            $table->string('contributor_email')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->text('message')->nullable();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('commission_minor')->default(0);

            $table->string('status')->default('pending'); // pending | successful | failed | refunded
            // payment_id FK added in a later migration (payments table created after this one).
            $table->unsignedBigInteger('payment_id')->nullable();

            $table->string('receipt_number')->nullable()->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
