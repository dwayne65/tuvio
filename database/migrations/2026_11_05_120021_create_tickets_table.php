<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();

            // Public ticket code shown to the attendee (human readable, unique).
            $table->string('code')->unique();
            // Secret, non-guessable token embedded in the QR (opaque; HMAC-verified on scan).
            $table->string('qr_token', 128)->unique();

            $table->string('attendee_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->string('seat_info')->nullable();

            $table->string('status')->default('issued');  // issued | used | void | refunded
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
