<?php

use App\Http\Controllers\Api\V1\Mahasiswa\ActivityLogController;
use App\Http\Controllers\Api\V1\Mahasiswa\PrestasiController;
use App\Http\Controllers\Api\V1\Mahasiswa\RekognisiController;
use App\Http\Controllers\Api\V1\Mahasiswa\SertifikasiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mahasiswa Routes — Endpoint Mahasiswa
|--------------------------------------------------------------------------
| Prefix     : /v1/mahasiswa  (didefinisikan di api.php)
| Middleware : auth:sanctum   (didefinisikan di api.php)
| Role       : mahasiswa
*/

Route::middleware('role:mahasiswa')->group(function () {

    // --- Prestasi Mandiri ---
    Route::prefix('prestasi')->controller(PrestasiController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // --- Sertifikasi ---
    Route::prefix('sertifikasi')->controller(SertifikasiController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // --- Rekognisi ---
    Route::prefix('rekognisi')->controller(RekognisiController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // --- Activity Log ---
    Route::prefix('activity-log')->controller(ActivityLogController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
    });

});
