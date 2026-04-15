<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel Master Dosen (Schema_Database.md §Tabel 3).
     * NUPTK/NIDN sebagai primary key.
     */
    public function up(): void
    {
        Schema::create('dosen', function (Blueprint $table) {
            $table->string('nuptk')->primary();
            $table->string('nama');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen');
    }
};
