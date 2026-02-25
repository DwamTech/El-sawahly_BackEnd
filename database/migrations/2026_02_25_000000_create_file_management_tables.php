<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Upload Sessions Table
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('session_id', 36)->unique();
            $table->enum('status', ['active', 'completed', 'expired', 'cancelled'])->default('active');
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('completed_files')->default(0);
            $table->unsignedInteger('failed_files')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
            $table->index('expires_at');
        });

        // 2. Files Table
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('file_name');
            $table->string('original_name');
            $table->enum('file_type', ['image', 'video', 'audio', 'document', 'unclassified']);
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->text('file_path');
            $table->text('file_url');
            $table->enum('status', ['pending', 'uploading', 'completed', 'failed'])->default('pending');
            $table->string('upload_session_id', 36)->nullable();
            $table->unsignedInteger('total_chunks')->default(1);
            $table->unsignedInteger('uploaded_chunks')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('file_type');
            $table->index('status');
            $table->index('upload_session_id');
            $table->index('created_at');
        });

        // 3. File Chunks Table
        Schema::create('file_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->onDelete('cascade');
            $table->unsignedInteger('chunk_index');
            $table->text('chunk_path');
            $table->unsignedInteger('chunk_size');
            $table->timestamp('uploaded_at');

            $table->unique(['file_id', 'chunk_index'], 'unique_file_chunk');
            $table->index('file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_chunks');
        Schema::dropIfExists('files');
        Schema::dropIfExists('upload_sessions');
    }
};
