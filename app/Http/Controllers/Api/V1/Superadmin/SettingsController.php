<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Superadmin\SettingsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Settings Superadmin (Kontrak_API_Frontend.md §E.13-14).
 * Thin Controller — delegasi logika ke SettingsService.
 */
class SettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    /**
     * [GET] Cek akun sinkronisasi Kemdikbud yang aktif.
     */
    public function showKemdikbud(): JsonResponse
    {
        $data = $this->settingsService->getKemdikbudCredentials();

        return $this->successResponse($data, 'Data kredensial aktif.');
    }

    /**
     * [PUT] Update akun Kemdikbud (email & password).
     */
    public function updateKemdikbud(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $this->settingsService->updateKemdikbudCredentials(
            $request->email,
            $request->password
        );

        return $this->successResponse(null, 'Akun integrasi Kemdikbud berhasil diupdate.');
    }
}
