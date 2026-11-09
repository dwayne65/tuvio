<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->string('description');                 // snapshot of ticket type name
            $table->bigInteger('unit_price_minor');        // snapshot of price at purchase
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('quantity');
            $table->bigInteger('line_total_minor');
            $table->timestamps();

            $table->index('order_id');
            $table->index('ticket_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
