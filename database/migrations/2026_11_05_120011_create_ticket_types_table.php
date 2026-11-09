<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // Regular | VIP | Early Bird | Student ...
            $table->string('kind')->default('paid');      // free | paid
            $table->text('description')->nullable();

            $table->bigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('UGX');

            // Inventory. quantity_total null = unlimited. sold/reserved are maintained atomically.
            $table->unsignedInteger('quantity_total')->nullable();
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);

            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->default(10);

            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();

            $table->string('status')->default('active');  // active | paused | sold_out | hidden
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'status', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
