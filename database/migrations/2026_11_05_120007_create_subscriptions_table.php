<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();

            $table->string('status')->default('trialing'); // trialing | active | past_due | canceled | expired
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);

            // Snapshot of price at subscription time (plans can change later)
            $table->bigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('interval')->default('monthly');

            $table->string('provider')->nullable();
            $table->string('provider_ref')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
