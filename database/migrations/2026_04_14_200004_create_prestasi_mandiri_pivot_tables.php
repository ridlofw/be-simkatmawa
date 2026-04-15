<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot tables untuk relasi Many-to-Many Prestasi Mandiri (Schema_Database.md §Tabel 5 & 6).
     * Satu prestasi bisa melibatkan banyak mahasiswa (kelompok) dan banyak dosen pembimbing.
     */
    public function up(): void
    {
        // Pivot: Prestasi <-> Mahasiswa
        Schema::create('prestasi_mandiri_mahasiswa', function (Blueprint $table) {
            $table->foreignId('prestasi_mandiri_id')->constrained('prestasi_mandiri')->cascadeOnDelete();
            $table->string('nim');
            $table->foreign('nim')->references('nim')->on('mahasiswa')->cascadeOnDelete();
            $table->primary(['prestasi_mandiri_id', 'nim']);
        });

        // Pivot: Prestasi <-> Dosen (termasuk url_surat_tugas wajib Kemdikbud)
        Schema::create('prestasi_mandiri_dosen', function (Blueprint $table) {
            $table->foreignId('prestasi_mandiri_id')->constrained('prestasi_mandiri')->cascadeOnDelete();
            $table->string('nuptk');
            $table->foreign('nuptk')->references('nuptk')->on('dosen')->cascadeOnDelete();
            $table->string('url_surat_tugas'); // Wajib Kemdikbud
            $table->primary(['prestasi_mandiri_id', 'nuptk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestasi_mandiri_dosen');
        Schema::dropIfExists('prestasi_mandiri_mahasiswa');
    }
};
