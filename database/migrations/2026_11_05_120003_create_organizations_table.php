<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();

            // Locale / finance defaults
            $table->char('country', 2)->nullable();
            $table->char('default_currency', 3)->default('UGX');

            // Verification + lifecycle
            $table->string('status')->default('active');            // active | suspended | pending
            $table->string('verification_status')->default('unverified'); // unverified | pending | verified | rejected
            $table->timestamp('verified_at')->nullable();

            // Platform commission override (basis points, e.g. 500 = 5.00%). Null = use platform default.
            $table->unsignedInteger('commission_bps')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
