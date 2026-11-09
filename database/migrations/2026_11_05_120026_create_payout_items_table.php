<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');        // organizer-net amount included in this payout
            $table->char('currency', 3)->default('UGX');
            $table->timestamps();

            $table->index('payout_id');
            // An order/contribution can only ever belong to one payout.
            $table->unique('order_id');
            $table->unique('contribution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
