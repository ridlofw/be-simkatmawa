<?php

use App\Http\Controllers\Api\V1\Admin\VerifikasiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| Admin Routes — Endpoint Admin / Verifikator
|--------------------------------------------------------------------------
| Prefix     : /v1/admin      (didefinisikan di api.php)
| Middleware : auth:sanctum    (didefinisikan di api.php)
| Role       : admin | superadmin
*/

Route::middleware('role:admin|superadmin')->group(function () {

    // --- Daftar Pengajuan (Unified) ---
    Route::prefix('pengajuan')->controller(VerifikasiController::class)->group(function () {
        Route::get('/{tipeKegiatan}', 'index');
        Route::get('/{tipeKegiatan}/export', 'export');  // HARUS sebelum /{id} agar tidak ter-capture
        Route::get('/{tipeKegiatan}/{id}', 'show');
    });

    Route::post('/verifikasi/{tipeKegiatan}/{id}', [VerifikasiController::class, 'verifikasi']);

    // --- Activity Log ---
    Route::prefix('activity-log')->controller(\App\Http\Controllers\Api\V1\Admin\ActivityLogController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
    });

    // --- Sync Queue Monitoring ---
    Route::prefix('sync-queue')->controller(\App\Http\Controllers\Api\V1\Admin\SyncQueueController::class)->group(function () {
        Route::get('/stats', 'stats');
        Route::get('/', 'index');
        Route::post('/{id}/retry', 'retry');
        Route::post('/retry-all', 'retryAll');
    });

});
Route::get('/dashboard', [DashboardController::class, 'index']);