<?php

namespace App\Models;

use App\Enums\SyncErrorCode;
use App\Enums\SyncQueueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model SyncQueue — Merepresentasikan item dalam antrean sinkronisasi ke Kemdiktisaintek.
 *
 * Polymorphic relation ke PrestasiMandiri, Sertifikasi, atau Rekognisi.
 * Mengelola lifecycle operasional queue terpisah dari status bisnis.
 */
class SyncQueue extends Model
{
    protected $table = 'sync_queue';

    protected $fillable = [
        'syncable_type',
        'syncable_id',
        'status',
        'priority',
        'attempt_count',
        'max_attempts',
        'next_retry_at',
        'error_code',
        'error_message',
        'error_detail',
        'kemdikbud_id',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncQueueStatus::class,
            'error_code' => SyncErrorCode::class,
            'error_detail' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    // ========== RELASI ==========

    /**
     * Polymorphic relation ke model sumber data (PrestasiMandiri, Sertifikasi, Rekognisi).
     */
    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }

    // ========== SCOPES ==========

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::PENDING);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::PROCESSING);
    }

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::SUCCESS);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::FAILED);
    }

    public function scopeFailedPermanent(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::FAILED_PERMANENT);
    }

    public function scopeRetryWaiting(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::RETRY_WAITING);
    }

    /**
     * Item yang siap diproses: pending ATAU retry_waiting yang waktunya sudah tiba.
     */
    public function scopeReadyToProcess(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', SyncQueueStatus::PENDING)
              ->orWhere(function (Builder $q2) {
                  $q2->where('status', SyncQueueStatus::RETRY_WAITING)
                     ->where('next_retry_at', '<=', now());
              });
        });
    }

    /**
     * Semua item gagal yang bisa di-retry oleh admin.
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', SyncQueueStatus::FAILED);
    }

    // ========== STATUS MUTATORS ==========

    /**
     * Tandai item sebagai sedang diproses.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => SyncQueueStatus::PROCESSING,
            'started_at' => now(),
            'error_code' => null,
            'error_message' => null,
            'error_detail' => null,
        ]);
    }

    /**
     * Tandai item sebagai berhasil sinkronisasi.
     */
    public function markAsSuccess(int $kemdikbudId): void
    {
        $this->update([
            'status' => SyncQueueStatus::SUCCESS,
            'kemdikbud_id' => $kemdikbudId,
            'completed_at' => now(),
            'error_code' => null,
            'error_message' => null,
            'error_detail' => null,
        ]);
    }

    /**
     * Tandai item sebagai gagal sementara (akan di-retry).
     */
    public function markAsRetryWaiting(SyncErrorCode $errorCode, string $errorMessage, ?array $errorDetail = null): void
    {
        $attempt = $this->attempt_count + 1;

        // Exponential backoff: 5 menit, 10 menit, 15 menit
        $backoffMinutes = $attempt * 5;

        $this->update([
            'status' => SyncQueueStatus::RETRY_WAITING,
            'attempt_count' => $attempt,
            'next_retry_at' => now()->addMinutes($backoffMinutes),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'error_detail' => $errorDetail,
            'completed_at' => null,
        ]);
    }

    /**
     * Tandai item sebagai gagal permanen (butuh manual retry dari admin).
     */
    public function markAsFailed(SyncErrorCode $errorCode, string $errorMessage, ?array $errorDetail = null): void
    {
        $this->update([
            'status' => SyncQueueStatus::FAILED,
            'attempt_count' => $this->attempt_count + 1,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'error_detail' => $errorDetail,
            'completed_at' => now(),
        ]);
    }

    /**
     * Tandai item sebagai gagal permanen karena data error (422).
     * Tidak bisa di-retry karena data harus diperbaiki.
     */
    public function markAsFailedPermanent(SyncErrorCode $errorCode, string $errorMessage, ?array $errorDetail = null): void
    {
        $this->update([
            'status' => SyncQueueStatus::FAILED_PERMANENT,
            'attempt_count' => $this->attempt_count + 1,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'error_detail' => $errorDetail,
            'completed_at' => now(),
        ]);
    }

    /**
     * Reset item untuk retry manual oleh admin.
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status' => SyncQueueStatus::PENDING,
            'attempt_count' => 0,
            'next_retry_at' => null,
            'error_code' => null,
            'error_message' => null,
            'error_detail' => null,
            'started_at' => null,
            'completed_at' => null,
            'queued_at' => now(),
        ]);
    }

    // ========== HELPERS ==========

    /**
     * Resolve tipe kegiatan dari syncable_type untuk payload building.
     */
    public function getSyncType(): string
    {
        return match ($this->syncable_type) {
            'App\\Models\\PrestasiMandiri' => 'prestasi',
            'App\\Models\\Sertifikasi' => 'sertifikasi',
            'App\\Models\\Rekognisi' => 'rekognisi',
            default => throw new \InvalidArgumentException("Unknown syncable type: {$this->syncable_type}"),
        };
    }

    /**
     * Label kategori yang human-readable untuk frontend.
     */
    public function getKategoriLabel(): string
    {
        return match ($this->syncable_type) {
            'App\\Models\\PrestasiMandiri' => 'Prestasi Mandiri',
            'App\\Models\\Sertifikasi' => 'Sertifikasi',
            'App\\Models\\Rekognisi' => 'Rekognisi',
            default => 'Unknown',
        };
    }
}
