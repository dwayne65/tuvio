<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per page a visitor opens. No IP address or cookie is kept: a visitor is a
     * salted hash that changes every day, so it counts unique visitors per day only.
     */
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->date('visited_on')->index();
            $table->string('path', 255);
            $table->string('visitor', 32);
            $table->boolean('is_entry')->default(false); // first page of a visit (a full page load)
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 10);
            $table->timestamp('created_at')->nullable();

            $table->index(['visited_on', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
