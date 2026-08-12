<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom `urutan` ke semua pivot table mahasiswa.
     *
     * Kolom ini menentukan posisi/hierarki anggota dalam kelompok:
     * - urutan 0 = Ketua (posisi tertinggi)
     * - urutan 1 = Anggota 1
     * - urutan 2 = Anggota 2
     * - dst.
     *
     * Nilai di-assign otomatis oleh BE berdasarkan index array
     * yang dikirim oleh FE saat create/update.
     */
    public function up(): void
    {
        Schema::table('prestasi_mandiri_mahasiswa', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan')->default(0)->after('nim');
        });

        Schema::table('rekognisi_mahasiswa', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan')->default(0)->after('nim');
        });

        Schema::table('sertifikasi_mahasiswa', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan')->default(0)->after('nim');
        });
    }

    public function down(): void
    {
        Schema::table('prestasi_mandiri_mahasiswa', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });

        Schema::table('rekognisi_mahasiswa', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });

        Schema::table('sertifikasi_mahasiswa', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
