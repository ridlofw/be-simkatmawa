<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel Master Mahasiswa (Schema_Database.md §Tabel 2).
     * NIM sebagai primary key karena merupakan identitas unik mahasiswa.
     */
    public function up(): void
    {
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->string('nim')->primary();
            $table->string('nama');
            $table->foreignUuid('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};
