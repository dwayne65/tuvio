<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New organizers start "pending" and get no dashboard until a platform admin
     * approves them, and every event goes through admin review before it goes live
     * ("in_review", then "published" or "rejected"). Existing organizations and
     * events keep their current status.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('verified_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable()->after('approved_by');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable()->after('published_at');
            $table->timestamp('reviewed_at')->nullable()->after('review_requested_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_requested_at', 'reviewed_at', 'review_note']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'rejection_reason']);
        });
    }
};
