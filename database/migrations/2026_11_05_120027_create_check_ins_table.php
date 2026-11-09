<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();

            // Every scan attempt is logged (valid or not) for the staff activity log.
            $table->string('result');    // valid | duplicate | invalid | wrong_event | unpaid | void
            $table->string('gate')->nullable();
            $table->string('device_id')->nullable();

            // Offline-capable sync: client supplies a UUID + the time it happened offline.
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('scanned_at');              // authoritative scan time (may be offline time)
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'result']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
