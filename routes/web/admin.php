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

    // --- Verifikasi Pengajuan ---
    Route::prefix('pengajuan')->controller(VerifikasiController::class)->group(function () {
        Route::get('/{tipeKegiatan}', 'index');
        Route::get('/{tipeKegiatan}/{id}', 'show');
    });

    Route::post('/verifikasi/{tipeKegiatan}/{id}', [VerifikasiController::class, 'verifikasi']);

});
Route::get('/dashboard', [DashboardController::class, 'index']);