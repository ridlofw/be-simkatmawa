<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel Transaksi Sertifikasi (Schema_Database.md §Tabel 7).
     * Struktur field mengikuti endpoint Kemdikbud POST /api/sertifikasi.
     */
    public function up(): void
    {
        Schema::create('sertifikasi', function (Blueprint $table) {
            $table->id();

            // === Field Kemdikbud ===
            $table->enum('level', ['KAB', 'PROV', 'NAS', 'INT']);
            $table->string('nama');           // Nama sertifikasi
            $table->string('penyelenggara');
            $table->string('url_peserta');
            $table->string('url_sertifikat');
            $table->date('tgl_sertifikat');
            $table->string('url_foto_upp');
            $table->string('url_dokumen_undangan');
            $table->text('keterangan')->nullable();

            // === Field Internal Udinus (PRD §3) ===
            $table->enum('status_internal', ['PENDING', 'REJECTED', 'APPROVED_UNSYNCED', 'SYNC_SUCCESS', 'SYNC_FAILED'])
                  ->default('PENDING');
            $table->text('alasan_penolakan')->nullable();
            $table->unsignedBigInteger('pusat_kemdikbud_id')->nullable()
                  ->comment('ID dari response API Kemdikbud saat SYNC_SUCCESS');

            // === Audit Trail ===
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status_internal');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikasi');
    }
};
