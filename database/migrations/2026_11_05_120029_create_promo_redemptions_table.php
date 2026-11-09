<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_discounted_minor');
            $table->char('currency', 3)->default('UGX');
            $table->timestamps();

            $table->unique('order_id');  // one promo per order
            $table->index('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
    }
};
