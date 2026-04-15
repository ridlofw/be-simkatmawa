<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel Transaksi Prestasi Mandiri (Schema_Database.md §Tabel 4).
     * Struktur field mengikuti spesifikasi endpoint Kemdikbud POST /api/prestasi-mandiri.
     * Field internal Udinus ditambahkan untuk state machine & audit trail.
     */
    public function up(): void
    {
        Schema::create('prestasi_mandiri', function (Blueprint $table) {
            $table->id();

            // === Field Kemdikbud ===
            $table->enum('level', ['KAB', 'PROV', 'NAS', 'INT']);
            $table->enum('kategori', ['RISNOV', 'RISNOVSSH', 'SENBUD', 'OLAHRAGA', 'MINAT']);
            $table->string('lomba');          // Nama lomba/kompetisi
            $table->string('cabang');         // Cabang/bidang lomba
            $table->string('penyelenggara');
            $table->enum('peringkat', ['JUARA1', 'JUARA2', 'JUARA3', 'HARAPAN1', 'HARAPAN2', 'HARAPAN3', 'APRESIASI', 'PESERTA']);
            $table->integer('jumlah_unit_peserta');
            $table->enum('kelompok_prestasi', ['INDIVIDU', 'KELOMPOK']);
            $table->enum('bentuk', ['DARING', 'LURING']);
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

            // === Indexes untuk performa query Admin ===
            $table->index('status_internal');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestasi_mandiri');
    }
};
