<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pending invites to people without an account yet have no user until they accept. */
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->index(['organization_id', 'invite_email']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'invite_email']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
