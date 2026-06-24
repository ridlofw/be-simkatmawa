<?php

namespace App\Services\Sync;

use App\Enums\SyncQueueStatus;
use App\Models\Setting;
use App\Models\SyncQueue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Service Layer — SyncQueue Management.
 *
 * Tanggung jawab:
 * 1. Enqueue item baru setelah admin approve
 * 2. Menyediakan data untuk dashboard monitoring (stats, list)
 * 3. Retry / Retry All mekanisme
 * 4. Play/Pause toggle
 */
class SyncQueueService
{
    /**
     * Masukkan item baru ke antrean sinkronisasi.
     * Dipanggil dari VerifikasiService saat admin approve.
     */
    public function enqueue(Model $record): SyncQueue
    {
        return SyncQueue::create([
            'syncable_type' => get_class($record),
            'syncable_id' => $record->id,
            'status' => SyncQueueStatus::PENDING,
            'queued_at' => now(),
        ]);
    }

    /**
     * Statistik untuk 4 card di dashboard frontend.
     */
    public function getStats(): array
    {
        $counts = SyncQueue::query()
            ->selectRaw("
                SUM(CASE WHEN status IN ('pending', 'retry_waiting') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status IN ('failed', 'failed_permanent') THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        // Optimasi: Ambil semua setting sekaligus (menghindari 4 query terpisah/N+1)
        $settings = Setting::whereIn('key', [
            'sync_queue_paused',
            'sync_queue_paused_by',
            'sync_queue_paused_at',
            'sync_queue_pause_reason'
        ])->pluck('value', 'key');

        return [
            'pending' => (int) ($counts->pending ?? 0),
            'processing' => (int) ($counts->processing ?? 0),
            'success' => (int) ($counts->success ?? 0),
            'failed' => (int) ($counts->failed ?? 0),
            'is_paused' => ($settings['sync_queue_paused'] ?? 'false') === 'true',
            'paused_by' => $settings['sync_queue_paused_by'] ?? null,
            'paused_at' => $settings['sync_queue_paused_at'] ?? null,
            'pause_reason' => $settings['sync_queue_pause_reason'] ?? null,
        ];
    }

    /**
     * Daftar antrean dengan filter dan paginasi untuk tabel frontend.
     */
    public function getQueue(?string $status, int $limit = 15): LengthAwarePaginator
    {
        $query = SyncQueue::with(['syncable.mahasiswa'])
            ->orderByDesc('queued_at');

        // Filter berdasarkan status
        if ($status && $status !== 'all') {
            $statusMap = [
                'menunggu' => [SyncQueueStatus::PENDING->value, SyncQueueStatus::RETRY_WAITING->value],
                'proses' => [SyncQueueStatus::PROCESSING->value],
                'berhasil' => [SyncQueueStatus::SUCCESS->value],
                'gagal' => [SyncQueueStatus::FAILED->value, SyncQueueStatus::FAILED_PERMANENT->value],
            ];

            if (isset($statusMap[$status])) {
                $query->whereIn('status', $statusMap[$status]);
            } else {
                // Fallback: coba langsung sebagai enum value
                $query->where('status', $status);
            }
        }

        return $query->paginate($limit);
    }

    /**
     * Retry single item yang gagal.
     */
    public function retry(int $id): ?SyncQueue
    {
        $item = SyncQueue::find($id);

        if (!$item || !$item->status->isRetryable()) {
            return null;
        }

        $previousStatus = $item->status->value;
        $item->resetForRetry();

        // Audit log: catat siapa yang retry item ini
        activity()
            ->causedBy(auth()->user())
            ->performedOn($item)
            ->useLog('sync-queue')
            ->event('retry')
            ->withProperties([
                'sync_queue_id' => $item->id,
                'previous_status' => $previousStatus,
                'syncable_type' => $item->syncable_type,
                'syncable_id' => $item->syncable_id,
            ])
            ->log('Retry sync queue item');

        return $item->fresh();
    }

    /**
     * Retry semua item yang gagal (hanya status 'failed', bukan 'failed_permanent').
     */
    public function retryAllFailed(): int
    {
        $failedItems = SyncQueue::retryable()->get();

        foreach ($failedItems as $item) {
            $item->resetForRetry();
        }

        $count = $failedItems->count();

        // Audit log: catat siapa yang retry semua item gagal
        if ($count > 0) {
            activity()
                ->causedBy(auth()->user())
                ->useLog('sync-queue')
                ->event('retry-all')
                ->withProperties([
                    'retried_count' => $count,
                    'retried_ids' => $failedItems->pluck('id')->toArray(),
                ])
                ->log("Retry semua item gagal ({$count} item)");
        }

        return $count;
    }

    /**
     * Toggle play/pause status queue.
     */
    public function togglePause(string $action, ?string $userId = null): array
    {
        $isPausing = $action === 'pause';

        Setting::setValue('sync_queue_paused', $isPausing ? 'true' : 'false');

        if ($isPausing) {
            Setting::setValue('sync_queue_paused_by', $userId);
            Setting::setValue('sync_queue_paused_at', now()->toIso8601String());
            Setting::setValue('sync_queue_pause_reason', 'MANUAL');
        } else {
            Setting::setValue('sync_queue_paused_by', null);
            Setting::setValue('sync_queue_paused_at', null);
            Setting::setValue('sync_queue_pause_reason', null);
        }

        // Audit log: catat siapa yang play/pause queue
        activity()
            ->causedBy(auth()->user())
            ->useLog('sync-queue')
            ->event($action)
            ->withProperties([
                'action' => $action,
                'reason' => 'MANUAL',
            ])
            ->log($isPausing ? 'Sync queue di-pause' : 'Sync queue di-resume');

        return [
            'is_paused' => $isPausing,
            'action' => $action,
        ];
    }

    /**
     * Cek apakah queue sedang di-pause.
     */
    public function isPaused(): bool
    {
        return Setting::getValue('sync_queue_paused', 'false') === 'true';
    }

    /**
     * Auto-pause queue karena error kritis (AUTH_ERROR, RATE_LIMIT).
     */
    public function autoPause(string $reason): void
    {
        Setting::setValue('sync_queue_paused', 'true');
        Setting::setValue('sync_queue_paused_by', 'SYSTEM');
        Setting::setValue('sync_queue_paused_at', now()->toIso8601String());
        Setting::setValue('sync_queue_pause_reason', $reason);

        // Audit log: catat auto-pause oleh sistem
        activity()
            ->useLog('sync-queue')
            ->event('auto-pause')
            ->withProperties([
                'action' => 'pause',
                'reason' => $reason,
                'triggered_by' => 'SYSTEM',
            ])
            ->log("Sync queue auto-pause: {$reason}");
    }

    /**
     * Ambil item berikutnya yang siap diproses (FIFO).
     * Digunakan oleh ProcessSyncQueue command.
     */
    public function getNextItem(): ?SyncQueue
    {
        return SyncQueue::readyToProcess()
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->first();
    }

    /**
     * Cek apakah ada item yang sedang processing (untuk mencegah overlap).
     */
    public function hasProcessingItem(): bool
    {
        return SyncQueue::processing()->exists();
    }

    /**
     * Ambil detail satu item queue (untuk Superadmin, termasuk error_detail).
     */
    public function getDetail(int $id): ?SyncQueue
    {
        return SyncQueue::with(['syncable.mahasiswa', 'syncable.dosen'])->find($id);
    }
}
