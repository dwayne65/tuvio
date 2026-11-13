<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete(); // null = all org events
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('code');                                   // used as ?ref=CODE
            $table->unsignedInteger('commission_bps')->default(0);    // promoter's cut of attributed sales
            $table->unsignedInteger('clicks')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promoter_id')->nullable()->after('promo_code_id')->constrained()->nullOnDelete();
        });

        Schema::create('event_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete(); // one review per order
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('rating');                    // 1..5
            $table->text('body')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['event_id', 'is_visible']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete(); // null = platform support
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('open');                // open | answered | closed
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('event_reviews');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promoter_id');
        });
        Schema::dropIfExists('promoters');
    }
};
