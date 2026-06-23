<?php

namespace App\Console\Commands;

use App\Enums\SyncQueueStatus;
use App\Models\SyncQueue;
use Illuminate\Console\Command;

/**
 * Artisan Command — Retention Policy untuk tabel sync_queue.
 *
 * Menghapus (hard delete) record sync_queue dengan status 'success'
 * yang lebih dari 90 hari. Record gagal dipertahankan untuk audit trail.
 *
 * Data bisnis (prestasi/sertifikasi/rekognisi) TIDAK terpengaruh —
 * status_internal dan kemdikbud_id tetap tersimpan di tabel asli.
 *
 * Dijadwalkan: setiap hari tengah malam
 *   Schedule::command('sync:clean-history')->daily();
 */
class CleanSyncQueueHistory extends Command
{
    protected $signature = 'sync:clean-history
                            {--days=90 : Jumlah hari retensi untuk record sukses}
                            {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}';

    protected $description = 'Hapus record sync_queue sukses yang lebih dari N hari (retention policy)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');

        $cutoffDate = now()->subDays($days);

        $query = SyncQueue::where('status', SyncQueueStatus::SUCCESS)
            ->where('completed_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Tidak ada record untuk dihapus.');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->info("[DRY RUN] {$count} record sukses lebih dari {$days} hari akan dihapus.");
            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Berhasil menghapus {$deleted} record sync_queue sukses (> {$days} hari).");

        return self::SUCCESS;
    }
}
