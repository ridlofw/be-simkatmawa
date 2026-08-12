<?php

namespace App\Services\Kemdikbud;

use App\Enums\SyncErrorCode;
use App\Exceptions\Sync\SyncAuthException;
use App\Exceptions\Sync\SyncException;
use App\Exceptions\Sync\SyncServerException;
use App\Exceptions\Sync\SyncValidationException;
use App\Models\Setting;
use App\Services\Sync\SyncQueueService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk sinkronisasi data ke API Pusat Kemdiktisaintek.
 *
 * Tanggung jawab:
 * 1. Mengelola autentikasi token (login, cache, refresh on 401)
 * 2. Mengirim payload ke endpoint Kemdikti (prestasi, sertifikasi, rekognisi)
 * 3. Mengklasifikasi error dan melempar exception yang tepat
 * 4. Auto-pause queue pada auth failure
 *
 * Alur token (Diagram_Alur_Simkatmawa.md §3):
 * - Cek cache → jika expired → decrypt password dari DB → POST /login → cache token baru
 * - Jika 401 saat kirim data → flush cache → re-login → retry 1x → jika masih 401 → auto-pause
 */
class SyncService
{
    private const BASE_URL = 'https://simkatmawa.kemdiktisaintek.go.id';
    private const TOKEN_CACHE_KEY = 'kemdikbud_bearer_token';
    private const TOKEN_TTL_HOURS = 23; // Refresh sebelum expired (asumsi 24 jam)

    public function __construct(
        private readonly SyncQueueService $syncQueueService
    ) {}

    private function getDefaultHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
            'Cookie' => '',
        ];
    }

    /**
     * Ambil bearer token (dari cache atau request baru).
     *
     * @throws SyncAuthException Jika login ke Pusat gagal
     */
    public function getToken(): string
    {
        // Cek cache terlebih dahulu
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cachedToken) {
            return $cachedToken;
        }

        // Token expired/tidak ada → login ulang ke Pusat
        return $this->loginAndCacheToken();
    }

    /**
     * Login ke API Kemdikti dan cache token yang didapat.
     *
     * @throws SyncAuthException Jika kredensial salah atau login gagal
     */
    private function loginAndCacheToken(): string
    {
        $email = Setting::getValue('kemdikbud_email');
        $password = Setting::getValue('kemdikbud_password');

        if (!$email || !$password) {
            throw new SyncAuthException(
                'Kredensial Kemdikti belum dikonfigurasi oleh Superadmin.',
                ['reason' => 'credentials_not_configured']
            );
        }

        // Decrypt password yang tersimpan terenkripsi
        try {
            $decryptedPassword = decrypt($password);
        } catch (\Exception $e) {
            throw new SyncAuthException(
                'Password Kemdikti tidak bisa didekripsi. Silakan update ulang di Settings.',
                ['reason' => 'decrypt_failed', 'error' => $e->getMessage()]
            );
        }

        try {
            $response = Http::withHeaders($this->getDefaultHeaders())
                ->timeout(30)->post(self::BASE_URL . '/api/login', [
                'email' => $email,
                'password' => $decryptedPassword,
            ]);
        } catch (ConnectionException $e) {
            throw new SyncServerException(
                'Tidak bisa terhubung ke server Kemdikti saat login.',
                SyncErrorCode::NETWORK_ERROR,
                ['error' => $e->getMessage()]
            );
        }

        if (!$response->successful() || !$response->json('token')) {
            Log::error('Kemdikbud login failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new SyncAuthException(
                'Gagal login ke API Kemdikti. Periksa email dan password di Settings.',
                [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]
            );
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
     * @throws SyncException Jika pengiriman gagal
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
     *
     * Implementasi token refresh on 401:
     * 1. Kirim request dengan token dari cache
     * 2. Jika 401 → flush cache → re-login → retry 1x
     * 3. Jika masih 401 → auto-pause queue → throw SyncAuthException
     *
     * @throws SyncAuthException Jika autentikasi gagal (auto-pause queue)
     * @throws SyncValidationException Jika data tidak valid (422)
     * @throws SyncServerException Jika server Kemdikti error (5xx)
     */
    private function sendToKemdikbud(string $endpoint, array $payload): array
    {
        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->withHeaders($this->getDefaultHeaders())
                ->timeout(30)
                ->post(self::BASE_URL . $endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new SyncServerException(
                'Koneksi ke Kemdikti gagal: ' . $e->getMessage(),
                SyncErrorCode::NETWORK_ERROR,
                ['error' => $e->getMessage(), 'endpoint' => $endpoint]
            );
        }

        // Token expired? Refresh dan coba 1x lagi
        if ($response->status() === 401) {
            Log::warning('Kemdikbud token expired, attempting refresh', ['endpoint' => $endpoint]);

            Cache::forget(self::TOKEN_CACHE_KEY);

            try {
                $token = $this->loginAndCacheToken();
            } catch (SyncAuthException $e) {
                // Login gagal → auto-pause queue
                $this->syncQueueService->autoPause('AUTH_FAILURE');
                throw $e;
            }

            // Retry request dengan token baru
            try {
                $response = Http::withToken($token)
                    ->withHeaders($this->getDefaultHeaders())
                    ->timeout(30)
                    ->post(self::BASE_URL . $endpoint, $payload);
            } catch (ConnectionException $e) {
                throw new SyncServerException(
                    'Koneksi ke Kemdikti gagal saat retry: ' . $e->getMessage(),
                    SyncErrorCode::NETWORK_ERROR,
                    ['error' => $e->getMessage(), 'endpoint' => $endpoint]
                );
            }

            // Masih 401 setelah refresh? Auth failure sesungguhnya
            if ($response->status() === 401 || $response->status() === 403) {
                $this->syncQueueService->autoPause('AUTH_FAILURE');

                throw new SyncAuthException(
                    'Kredensial Kemdikti tidak valid setelah refresh token.',
                    [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]
                );
            }
        }

        // Klasifikasi error berdasarkan HTTP status
        if (!$response->successful()) {
            $this->handleErrorResponse($response, $endpoint);
        }

        return $response->json();
    }

    /**
     * Handle error response dari Kemdikti dan throw exception yang sesuai.
     *
     * @throws SyncValidationException Jika 422
     * @throws SyncAuthException Jika 403
     * @throws SyncServerException Jika 429 / 5xx
     * @throws SyncException Jika error tidak dikenali
     */
    private function handleErrorResponse(Response $response, string $endpoint): void
    {
        $status = $response->status();
        $body = $response->json() ?? ['raw' => $response->body()];
        $logContext = ['endpoint' => $endpoint, 'status' => $status, 'body' => $body];

        Log::error('Kemdikbud sync failed', $logContext);

        match (true) {
            $status === 403 => throw new SyncAuthException(
                'Akses ditolak oleh API Kemdikti.',
                $logContext
            ),

            $status === 422 => throw new SyncValidationException(
                $this->extractValidationMessage($body),
                $logContext
            ),

            $status === 429 => (function () use ($logContext) {
                $this->syncQueueService->autoPause('RATE_LIMIT');
                throw new SyncServerException(
                    'Terlalu banyak request ke Kemdikti. Queue di-pause otomatis.',
                    SyncErrorCode::RATE_LIMIT,
                    $logContext
                );
            })(),

            $status >= 500 => throw new SyncServerException(
                "Server Kemdikti error (HTTP $status).",
                SyncErrorCode::SERVER_ERROR,
                $logContext
            ),

            default => throw new SyncException(
                "Error tidak diketahui dari Kemdikti (HTTP $status).",
                SyncErrorCode::UNKNOWN_ERROR,
                $logContext
            ),
        };
    }

    /**
     * Ekstrak pesan validasi yang human-readable dari response 422 Kemdikti.
     */
    private function extractValidationMessage(array $body): string
    {
        // Coba ambil pesan dari format standar Laravel
        if (isset($body['message'])) {
            return 'Kemdikti: ' . $body['message'];
        }

        // Coba ambil dari errors array
        if (isset($body['errors']) && is_array($body['errors'])) {
            $firstError = collect($body['errors'])->flatten()->first();
            if ($firstError) {
                return 'Kemdikti: ' . $firstError;
            }
        }

        return 'Data tidak sesuai format yang diterima Kemdikti.';
    }
}
