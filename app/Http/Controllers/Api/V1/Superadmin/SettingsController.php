<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Settings Superadmin (Kontrak_API_Frontend.md §E.13-14).
 * Mengelola kredensial integrasi API Kemdikbud Pusat.
 */
class SettingsController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Cek akun sinkronisasi Kemdikbud yang aktif.
     */
    public function showKemdikbud(): JsonResponse
    {
        $emailSetting = Setting::with('updater')->where('key', 'kemdikbud_email')->first();
        $passwordSet = !empty(Setting::getValue('kemdikbud_password'));

        $updaterName = '-';
        if ($emailSetting && $emailSetting->updater) {
            $updaterName = $emailSetting->updater->name . ' (' . ($emailSetting->updater->getRoleNames()->first() ?? '-') . ')';
        }

        return $this->successResponse([
            'email' => $emailSetting ? $emailSetting->value : null,
            'is_password_set' => $passwordSet,
            'terakhir_diperbarui' => $emailSetting ? $emailSetting->updated_at : null,
            'diperbarui_oleh' => $updaterName,
        ], 'Data kredensial aktif.');
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

        // Simpan dengan enkripsi untuk keamanan
        Setting::setValue('kemdikbud_email', $request->email);
        Setting::setValue('kemdikbud_password', encrypt($request->password));

        // Hapus token lama agar worker meminta token baru
        Setting::setValue('kemdikbud_bearer_token', null);
        Setting::setValue('token_expires_at', null);

        return $this->successResponse(null, 'Akun integrasi Kemdikbud berhasil diupdate.');
    }
}
