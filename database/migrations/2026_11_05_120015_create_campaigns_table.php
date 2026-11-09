<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('cover_image_path')->nullable();

            $table->bigInteger('goal_minor')->nullable();          // null = open-ended
            $table->bigInteger('raised_minor')->default(0);        // maintained atomically on success
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('contributors_count')->default(0);

            $table->string('status')->default('draft');            // draft | active | closed
            $table->timestamp('deadline_at')->nullable();
            $table->boolean('allow_anonymous')->default(true);
            $table->json('suggested_amounts_minor')->nullable();   // preset buttons

            // Commission on contributions is configured independently from ticket sales.
            $table->unsignedInteger('commission_bps')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deadline_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
