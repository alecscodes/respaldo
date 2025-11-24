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
        Schema::create('chunk_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id', 64)->unique()->comment('Unique identifier for the upload session');
            $table->foreignId('app_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->unsignedBigInteger('total_size')->comment('Total file size in bytes');
            $table->unsignedInteger('total_chunks')->comment('Total number of chunks expected');
            $table->unsignedInteger('chunk_size')->comment('Size of each chunk in bytes');
            $table->json('uploaded_chunks')->default('[]')->comment('Array of chunk indices that have been uploaded');
            $table->string('status', 20)->default('pending')->comment('pending, in_progress, completed, failed');
            $table->string('file_path')->nullable()->comment('Final file path after assembly');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('When to cleanup incomplete uploads');
            $table->timestamps();

            $table->index(['upload_id']);
            $table->index(['app_id', 'user_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunk_uploads');
    }
};
