<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();            // public URL slug
            $table->longText('description')->nullable();
            $table->string('cover_image_path')->nullable();

            $table->string('status')->default('draft');   // draft | published | unpublished | cancelled | completed
            $table->string('visibility')->default('public'); // public | unlisted | private
            $table->string('type')->default('physical');   // physical | online | hybrid

            // Timing
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->string('timezone')->default('UTC');

            // Location
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('city')->nullable();
            $table->char('country', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('online_url')->nullable();

            // Finance
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('commission_bps')->nullable(); // override; null = org/platform default
            $table->text('refund_policy')->nullable();

            // Capacity / discovery
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'start_at']);
            $table->index(['event_category_id', 'start_at']);
            $table->index(['is_featured', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
