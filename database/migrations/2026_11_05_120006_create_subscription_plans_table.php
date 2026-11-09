<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Starter | Professional | Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->bigInteger('price_minor')->default(0); // integer minor units
            $table->char('currency', 3)->default('UGX');
            $table->string('interval')->default('monthly'); // monthly | yearly
            $table->unsignedInteger('trial_days')->default(0);

            // Limits (null = unlimited)
            $table->unsignedInteger('event_limit')->nullable();
            $table->unsignedInteger('ticket_limit')->nullable();     // tickets per event
            $table->unsignedInteger('team_member_limit')->nullable();

            $table->json('features')->nullable();          // feature flags / bullet list
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);   // shown on pricing page
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_public', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
