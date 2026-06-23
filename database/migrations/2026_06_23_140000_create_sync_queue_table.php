<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel sync_queue — Mengelola antrean sinkronisasi data ke API Kemdiktisaintek.
 *
 * Terpisah dari tabel bisnis (prestasi/sertifikasi/rekognisi) agar:
 * 1. Query monitoring cepat (1 tabel, tanpa UNION)
 * 2. Data bisnis tetap bersih (SRP)
 * 3. History retry tersimpan lengkap
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference ke data asli (PrestasiMandiri, Sertifikasi, Rekognisi)
            $table->morphs('syncable');

            // Queue status & priority
            $table->string('status', 30)->default('pending')
                ->comment('pending|processing|success|retry_waiting|failed|failed_permanent');
            $table->integer('priority')->default(0)
                ->comment('Higher = processed first');

            // Retry tracking
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable()
                ->comment('Waktu retry berikutnya (exponential backoff)');

            // Error tracking
            $table->string('error_code', 50)->nullable()
                ->comment('AUTH_ERROR|VALIDATION_ERROR|SERVER_ERROR|NETWORK_ERROR|RATE_LIMIT|UNKNOWN_ERROR');
            $table->text('error_message')->nullable()
                ->comment('Pesan singkat untuk frontend');
            $table->json('error_detail')->nullable()
                ->comment('Full response body dari Kemdikti untuk debugging');

            // Hasil sukses
            $table->unsignedBigInteger('kemdikbud_id')->nullable()
                ->comment('ID dari response Kemdikti setelah sync berhasil');

            // Lifecycle timestamps
            $table->timestamp('queued_at')->useCurrent()
                ->comment('Kapan item masuk antrean');
            $table->timestamp('started_at')->nullable()
                ->comment('Kapan mulai diproses');
            $table->timestamp('completed_at')->nullable()
                ->comment('Kapan selesai (sukses/gagal permanen)');

            $table->timestamps();

            // Indexes untuk performa query
            $table->index('status');
            $table->index(['status', 'next_retry_at']);
            $table->index(['status', 'priority', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};
