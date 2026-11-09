<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('locale', 8)->default('en')->after('avatar_path');
            $table->char('country', 2)->nullable()->after('locale');     // ISO-3166 alpha-2
            $table->boolean('is_admin')->default(false)->after('country');
            $table->string('timezone')->default('UTC')->after('is_admin');

            // Two-factor (optional, per spec)
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->timestamp('last_login_at')->nullable();

            $table->index('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'avatar_path', 'locale', 'country', 'is_admin', 'timezone',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'last_login_at',
            ]);
        });
    }
};
