<?php

namespace App\Console\Commands;

use App\Jobs\SyncToKemdikbudJob;
use App\Services\Sync\SyncQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan Command — Memproses antrean sinkronisasi ke Kemdiktisaintek.
 *
 * Dijadwalkan setiap 1 menit via Laravel Scheduler.
 * Mengambil 1 item tertua yang siap diproses (FIFO) dan dispatch ke queue worker.
 *
 * Alur:
 * 1. Cek apakah queue di-pause → skip
 * 2. Cek apakah ada item sedang processing → skip (sequential, no overlap)
 * 3. Ambil 1 item ready (pending / retry_waiting yang waktunya tiba)
 * 4. Dispatch SyncToKemdikbudJob
 *
 * Registrasi scheduler: routes/console.php atau app/Console/Kernel.php
 *   Schedule::command('sync:process-queue')->everyMinute();
 */
class ProcessSyncQueue extends Command
{
    protected $signature = 'sync:process-queue';

    protected $description = 'Proses 1 item dari antrean sinkronisasi ke Kemdiktisaintek';

    public function __construct(
        private readonly SyncQueueService $syncQueueService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // 1. Cek apakah queue di-pause
        if ($this->syncQueueService->isPaused()) {
            $this->info('Sync queue sedang di-pause. Skipping.');
            return self::SUCCESS;
        }

        // 2. Cek apakah ada item yang sedang diproses (sequential processing)
        if ($this->syncQueueService->hasProcessingItem()) {
            $this->info('Ada item yang sedang diproses. Skipping untuk menghindari overlap.');
            return self::SUCCESS;
        }

        // 3. Ambil item berikutnya
        $item = $this->syncQueueService->getNextItem();

        if (!$item) {
            $this->info('Tidak ada item untuk diproses.');
            return self::SUCCESS;
        }

        // 4. Dispatch job ke queue worker
        $this->info("Memproses sync_queue #{$item->id} ({$item->getSyncType()} #{$item->syncable_id})");

        SyncToKemdikbudJob::dispatch($item);

        Log::info('ProcessSyncQueue: dispatched job', [
            'sync_queue_id' => $item->id,
            'type' => $item->getSyncType(),
            'syncable_id' => $item->syncable_id,
        ]);

        return self::SUCCESS;
    }
}
