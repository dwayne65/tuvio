<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_type')->nullable();
            $table->string('provider_ref')->nullable();      // tx id referenced by the event
            $table->string('signature')->nullable();

            // Dedupe key so the same webhook delivery is only processed once.
            $table->string('dedupe_key')->nullable()->unique();

            $table->json('payload');
            $table->string('status')->default('received');    // received | processed | invalid | duplicate | ignored
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
            $table->index(['provider', 'provider_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
