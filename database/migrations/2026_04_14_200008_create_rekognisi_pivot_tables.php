<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot tables untuk relasi Many-to-Many Rekognisi.
     */
    public function up(): void
    {
        Schema::create('rekognisi_mahasiswa', function (Blueprint $table) {
            $table->foreignId('rekognisi_id')->constrained('rekognisi')->cascadeOnDelete();
            $table->string('nim');
            $table->foreign('nim')->references('nim')->on('mahasiswa')->cascadeOnDelete();
            $table->primary(['rekognisi_id', 'nim']);
        });

        Schema::create('rekognisi_dosen', function (Blueprint $table) {
            $table->foreignId('rekognisi_id')->constrained('rekognisi')->cascadeOnDelete();
            $table->string('nuptk');
            $table->foreign('nuptk')->references('nuptk')->on('dosen')->cascadeOnDelete();
            $table->string('url_surat_tugas');
            $table->primary(['rekognisi_id', 'nuptk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekognisi_dosen');
        Schema::dropIfExists('rekognisi_mahasiswa');
    }
};
