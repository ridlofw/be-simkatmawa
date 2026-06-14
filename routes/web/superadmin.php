<?php

use App\Http\Controllers\Api\V1\Superadmin\SettingsController;
use App\Http\Controllers\Api\V1\Superadmin\TrashController;
use App\Http\Controllers\Api\V1\Superadmin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Superadmin Routes — Endpoint Superadmin
|--------------------------------------------------------------------------
| Prefix     : /v1/superadmin  (didefinisikan di api.php)
| Middleware : auth:sanctum     (didefinisikan di api.php)
| Role       : superadmin
*/

Route::middleware('role:superadmin')->group(function () {

    // --- Settings: Kredensial Kemdikbud ---
    Route::prefix('settings')->controller(SettingsController::class)->group(function () {
        Route::get('/kemdikbud', 'showKemdikbud');
        Route::put('/kemdikbud', 'updateKemdikbud');
    });

    // --- Recycle Bin: Soft Deleted Data ---
    Route::prefix('trash')->controller(TrashController::class)->group(function () {
        Route::get('/{tipeKegiatan}', 'index');
        Route::get('/{tipeKegiatan}/{id}', 'show');
        Route::put('/{tipeKegiatan}/{id}/restore', 'restore');
    });

    // --- User Management ---
    Route::prefix('users')->controller(UserController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

});
