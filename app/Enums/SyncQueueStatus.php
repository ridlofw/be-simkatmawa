<?php

namespace App\Enums;

/**
 * Status operasional untuk tabel sync_queue.
 *
 * Lifecycle:
 * pending → processing → success
 * pending → processing → retry_waiting → processing → success
 * pending → processing → retry_waiting → ... → failed (3x gagal, manual retry)
 * pending → processing → failed_permanent (422 validation error, jangan retry)
 */
enum SyncQueueStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case RETRY_WAITING = 'retry_waiting';
    case FAILED = 'failed';
    case FAILED_PERMANENT = 'failed_permanent';

    /**
     * Apakah status ini bisa di-retry oleh admin?
     */
    public function isRetryable(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Apakah status ini menandakan proses selesai (terminal state)?
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::SUCCESS,
            self::FAILED,
            self::FAILED_PERMANENT,
        ]);
    }

    /**
     * Label human-readable untuk frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::PROCESSING => 'Diproses',
            self::SUCCESS => 'Berhasil',
            self::RETRY_WAITING => 'Menunggu Retry',
            self::FAILED => 'Gagal',
            self::FAILED_PERMANENT => 'Gagal (Data Error)',
        };
    }
}
