<?php

use App\Http\Controllers\Api\V1\Admin\VerifikasiController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Mahasiswa\PrestasiController;
use App\Http\Controllers\Api\V1\Mahasiswa\RekognisiController;
use App\Http\Controllers\Api\V1\Mahasiswa\SertifikasiController;
use App\Http\Controllers\Api\V1\ReferensiController;
use App\Http\Controllers\Api\V1\Superadmin\SettingsController;
use App\Http\Controllers\Api\V1\Superadmin\TrashController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Simkatmawa Udinus
|--------------------------------------------------------------------------
| Versioning: v1 prefix (Kontrak_API_Frontend.md)
| Auth: Laravel Sanctum Bearer Token
| RBAC: role middleware (mahasiswa, admin, superadmin)
*/

Route::prefix('v1')->group(function () {

    // ==============================
    // A. ENDPOINT OTENTIKASI (Public)
    // ==============================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });

    // ==============================
    // PROTECTED ROUTES (Auth Required)
    // ==============================
    Route::middleware('auth:sanctum')->group(function () {

        // Auth — Authenticated
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // B. REFERENSI ENUM (Global — semua role)
        Route::get('/referensi/enums', [ReferensiController::class, 'enums']);

        // ==============================
        // C. ENDPOINT MAHASISWA
        // ==============================
        Route::middleware('role:mahasiswa')->prefix('mahasiswa')->group(function () {
            // Prestasi Mandiri
            Route::get('/prestasi', [PrestasiController::class, 'index']);
            Route::post('/prestasi', [PrestasiController::class, 'store']);
            Route::get('/prestasi/{id}', [PrestasiController::class, 'show']);
            Route::put('/prestasi/{id}', [PrestasiController::class, 'update']);
            Route::delete('/prestasi/{id}', [PrestasiController::class, 'destroy']);

            // Sertifikasi
            Route::get('/sertifikasi', [SertifikasiController::class, 'index']);
            Route::post('/sertifikasi', [SertifikasiController::class, 'store']);
            Route::get('/sertifikasi/{id}', [SertifikasiController::class, 'show']);
            Route::put('/sertifikasi/{id}', [SertifikasiController::class, 'update']);
            Route::delete('/sertifikasi/{id}', [SertifikasiController::class, 'destroy']);

            // Rekognisi
            Route::get('/rekognisi', [RekognisiController::class, 'index']);
            Route::post('/rekognisi', [RekognisiController::class, 'store']);
            Route::get('/rekognisi/{id}', [RekognisiController::class, 'show']);
            Route::put('/rekognisi/{id}', [RekognisiController::class, 'update']);
            Route::delete('/rekognisi/{id}', [RekognisiController::class, 'destroy']);
        });

        // ==============================
        // D. ENDPOINT ADMIN
        // ==============================
        Route::middleware('role:admin|superadmin')->prefix('admin')->group(function () {
            Route::get('/pengajuan/{tipeKegiatan}', [VerifikasiController::class, 'index']);
            Route::get('/pengajuan/{tipeKegiatan}/{id}', [VerifikasiController::class, 'show']);
            Route::post('/verifikasi/{tipeKegiatan}/{id}', [VerifikasiController::class, 'verifikasi']);
        });

        // ==============================
        // E. ENDPOINT SUPERADMIN
        // ==============================
        Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
            // Settings — Kredensial Kemdikbud
            Route::get('/settings/kemdikbud', [SettingsController::class, 'showKemdikbud']);
            Route::put('/settings/kemdikbud', [SettingsController::class, 'updateKemdikbud']);

            // Recycle Bin — Soft Deleted Data
            Route::get('/trash/{tipeKegiatan}', [TrashController::class, 'index']);
            Route::put('/trash/{tipeKegiatan}/{id}/restore', [TrashController::class, 'restore']);
            Route::delete('/trash/{tipeKegiatan}/{id}', [TrashController::class, 'forceDelete']);
        });
    });
});
