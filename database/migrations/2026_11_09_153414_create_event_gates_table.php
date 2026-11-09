<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named entrances per event. Check-ins keep storing the gate name in check_ins.gate.
        Schema::create('event_gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_gates');
    }
};
