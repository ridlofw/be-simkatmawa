<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot tables untuk relasi Many-to-Many Sertifikasi.
     */
    public function up(): void
    {
        Schema::create('sertifikasi_mahasiswa', function (Blueprint $table) {
            $table->foreignId('sertifikasi_id')->constrained('sertifikasi')->cascadeOnDelete();
            $table->string('nim');
            $table->foreign('nim')->references('nim')->on('mahasiswa')->cascadeOnDelete();
            $table->primary(['sertifikasi_id', 'nim']);
        });

        Schema::create('sertifikasi_dosen', function (Blueprint $table) {
            $table->foreignId('sertifikasi_id')->constrained('sertifikasi')->cascadeOnDelete();
            $table->string('nuptk');
            $table->foreign('nuptk')->references('nuptk')->on('dosen')->cascadeOnDelete();
            $table->string('url_surat_tugas');
            $table->primary(['sertifikasi_id', 'nuptk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikasi_dosen');
        Schema::dropIfExists('sertifikasi_mahasiswa');
    }
};
