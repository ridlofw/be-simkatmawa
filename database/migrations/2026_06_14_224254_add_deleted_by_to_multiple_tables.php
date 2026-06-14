<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['prestasi_mandiri', 'sertifikasi', 'rekognisi', 'users'];

        foreach ($tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'deleted_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('deleted_by')->nullable()->after('deleted_at');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['prestasi_mandiri', 'sertifikasi', 'rekognisi', 'users'];

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'deleted_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('deleted_by');
                });
            }
        }
    }
};
