<?php

namespace App\Enums;

/**
 * Klasifikasi error code dari sinkronisasi ke API Kemdiktisaintek.
 *
 * Setiap kategori error memiliki strategi penanganan yang berbeda:
 * - AUTH_ERROR / TOKEN_EXPIRED → jangan retry, pause queue
 * - VALIDATION_ERROR → jangan retry (data harus diperbaiki)
 * - SERVER_ERROR / NETWORK_ERROR → retry dengan backoff
 * - RATE_LIMIT → pause queue sementara
 */
enum SyncErrorCode: string
{
    case AUTH_ERROR = 'AUTH_ERROR';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case SERVER_ERROR = 'SERVER_ERROR';
    case NETWORK_ERROR = 'NETWORK_ERROR';
    case RATE_LIMIT = 'RATE_LIMIT';
    case UNKNOWN_ERROR = 'UNKNOWN_ERROR';

    /**
     * Apakah error ini bisa di-retry otomatis?
     */
    public function isRetryable(): bool
    {
        return in_array($this, [
            self::SERVER_ERROR,
            self::NETWORK_ERROR,
        ]);
    }

    /**
     * Apakah error ini harus mem-pause queue secara otomatis?
     */
    public function shouldAutoPause(): bool
    {
        return in_array($this, [
            self::AUTH_ERROR,
            self::RATE_LIMIT,
        ]);
    }

    /**
     * Pesan singkat human-readable untuk frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::AUTH_ERROR => 'Kredensial Kemdikti tidak valid',
            self::TOKEN_EXPIRED => 'Sesi Kemdikti habis',
            self::VALIDATION_ERROR => 'Data tidak sesuai format Kemdikti',
            self::SERVER_ERROR => 'Server Kemdikti sedang bermasalah',
            self::NETWORK_ERROR => 'Koneksi ke Kemdikti gagal',
            self::RATE_LIMIT => 'Terlalu banyak request ke Kemdikti',
            self::UNKNOWN_ERROR => 'Kesalahan tidak diketahui',
        };
    }
}
