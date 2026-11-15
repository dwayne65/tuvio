<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uploaded files (event covers, avatars) kept in the database for hosts whose disk is
     * wiped on every restart, such as Render's free plan. Contents are base64 text so they
     * bind the same way on SQLite and Postgres, with or without emulated prepares.
     */
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->string('path', 512)->unique();
            $table->longText('contents');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('visibility', 16)->default('public');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
