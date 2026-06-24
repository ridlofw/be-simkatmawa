<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom notifications table — kolom eksplisit untuk query & index efisien.
     * Tidak menggunakan default Laravel notifications table (kolom JSON `data`).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Klasifikasi (untuk FE mapping warna & grouping)
            $table->string('type', 20);        // Enum: success, warning, error, info
            $table->string('category', 50);    // Enum: submission_sent, submission_approved, etc.

            // Konten
            $table->string('title', 255);
            $table->text('message');
            $table->string('action_url', 500)->nullable();

            // Status baca
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Composite indexes untuk performa query
            $table->index(['user_id', 'read_at'], 'idx_user_read');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index('created_at', 'idx_cleanup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
