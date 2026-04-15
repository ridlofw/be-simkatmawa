<?php

namespace App\Services\Kemdikbud;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk sinkronisasi data ke API Pusat Kemdikbud.
 *
 * Tanggung jawab:
 * 1. Mengelola autentikasi token (login, cache, refresh)
 * 2. Mengirim payload ke endpoint Kemdikbud (prestasi, sertifikasi, rekognisi)
 *
 * Alur token (Diagram_Alur_Simkatmawa.md §3):
 * - Cek cache → jika expired → decrypt password dari DB → POST /login → cache token baru
 */
class SyncService
{
    private const BASE_URL = 'https://simkatmawa.kemdiktisaintek.go.id';
    private const TOKEN_CACHE_KEY = 'kemdikbud_bearer_token';
    private const TOKEN_TTL_HOURS = 23; // Refresh sebelum expired (asumsi 24 jam)

    /**
     * Ambil bearer token (dari cache atau request baru).
     *
     * @throws \Exception Jika login ke Pusat gagal
     */
    public function getToken(): string
    {
        // Cek cache terlebih dahulu
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cachedToken) {
            return $cachedToken;
        }

        // Token expired/tidak ada → login ulang ke Pusat
        $email = Setting::getValue('kemdikbud_email');
        $password = Setting::getValue('kemdikbud_password');

        if (!$email || !$password) {
            throw new \Exception('Kredensial Kemdikbud belum dikonfigurasi oleh Superadmin.');
        }

        // Decrypt password yang tersimpan terenkripsi
        $decryptedPassword = decrypt($password);

        $response = Http::post(self::BASE_URL . '/api/login', [
            'email' => $email,
            'password' => $decryptedPassword,
        ]);

        if (!$response->successful() || !$response->json('token')) {
            Log::error('Kemdikbud login failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Gagal login ke API Kemdikbud Pusat. Status: ' . $response->status());
        }

        $token = $response->json('token');

        // Simpan ke cache dengan TTL
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addHours(self::TOKEN_TTL_HOURS));

        return $token;
    }

    /**
     * Kirim data prestasi mandiri ke Pusat.
     *
     * @return array Response dari Kemdikbud
     * @throws \Exception Jika pengiriman gagal
     */
    public function syncPrestasi(array $payload): array
    {
        return $this->sendToKemdikbud('/api/prestasi-mandiri', $payload);
    }

    /**
     * Kirim data sertifikasi ke Pusat.
     */
    public function syncSertifikasi(array $payload): array
    {
        return $this->sendToKemdikbud('/api/sertifikasi', $payload);
    }

    /**
     * Kirim data rekognisi ke Pusat.
     */
    public function syncRekognisi(array $payload): array
    {
        return $this->sendToKemdikbud('/api/rekognisi', $payload);
    }

    /**
     * Method internal untuk mengirim HTTP POST ke Kemdikbud dengan token.
     */
    private function sendToKemdikbud(string $endpoint, array $payload): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->post(self::BASE_URL . $endpoint, $payload);

        if (!$response->successful()) {
            Log::error('Kemdikbud sync failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Gagal mengirim data ke Kemdikbud. Status: ' . $response->status());
        }

        return $response->json();
    }
}
