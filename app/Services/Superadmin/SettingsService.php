<?php

namespace App\Services\Superadmin;

use App\Models\Setting;

/**
 * Service Layer — Settings Superadmin (Kontrak_API_Frontend.md §E.13-14).
 * Mengelola logika kredensial integrasi API Kemdikbud Pusat.
 */
class SettingsService
{
    /**
     * Ambil data kredensial Kemdikbud yang aktif.
     */
    public function getKemdikbudCredentials(): array
    {
        $emailSetting = Setting::with('updater')->where('key', 'kemdikbud_email')->first();
        $passwordSet = !empty(Setting::getValue('kemdikbud_password'));

        $updaterName = '-';
        if ($emailSetting && $emailSetting->updater) {
            $updaterName = $emailSetting->updater->name . ' (' . ($emailSetting->updater->getRoleNames()->first() ?? '-') . ')';
        }

        return [
            'email' => $emailSetting ? $emailSetting->value : null,
            'is_password_set' => $passwordSet,
            'terakhir_diperbarui' => $emailSetting ? $emailSetting->updated_at : null,
            'diperbarui_oleh' => $updaterName,
        ];
    }

    /**
     * Update akun Kemdikbud (email & password).
     */
    public function updateKemdikbudCredentials(string $email, string $password): void
    {
        // Simpan dengan enkripsi untuk keamanan
        Setting::setValue('kemdikbud_email', $email);
        Setting::setValue('kemdikbud_password', encrypt($password));

        // Hapus token lama agar worker meminta token baru
        Setting::setValue('kemdikbud_bearer_token', null);
        Setting::setValue('token_expires_at', null);
    }
}
