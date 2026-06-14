<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ReferensiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes — Simkatmawa Udinus
|--------------------------------------------------------------------------
| Versioning : v1 prefix (Kontrak_API_Frontend.md)
| Auth       : Laravel Sanctum Bearer Token
| RBAC       : Spatie Laravel Permission (role middleware)
|
| Struktur modular — rute spesifik per-role dipisahkan ke folder
| routes/web/ agar kode lebih rapi dan mudah dikelola.
|
| routes/
| ├── api.php              ← File utama (auth, referensi, require sub-routes)
| └── web/
|     ├── mahasiswa.php    ← Prestasi, Sertifikasi, Rekognisi
|     ├── admin.php        ← Verifikasi Pengajuan
|     └── superadmin.php   ← Settings, Trash / Recycle Bin
|--------------------------------------------------------------------------
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
        Route::prefix('referensi')->controller(ReferensiController::class)->group(function () {
            Route::get('/enums', 'enums');
            Route::get('/mahasiswa', 'searchMahasiswa');
            Route::get('/dosen', 'searchDosen');
        });

        // ==============================
        // C. ENDPOINT MAHASISWA
        // ==============================
        Route::prefix('mahasiswa')->group(function () {
            require __DIR__ . '/web/mahasiswa.php';
        });

        // ==============================
        // D. ENDPOINT ADMIN
        // ==============================
        Route::prefix('admin')->group(function () {
            require __DIR__ . '/web/admin.php';
        });

        // ==============================
        // E. ENDPOINT SUPERADMIN
        // ==============================
        Route::prefix('superadmin')->group(function () {
            require __DIR__ . '/web/superadmin.php';
        });
    });
});
Route::get('/dashboard', [DashboardController::class, 'index']);