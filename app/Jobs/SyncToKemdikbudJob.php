<?php

namespace App\Jobs;

use App\Enums\StatusInternal;
use App\Exceptions\Sync\SyncAuthException;
use App\Exceptions\Sync\SyncException;
use App\Exceptions\Sync\SyncValidationException;
use App\Models\SyncQueue;
use App\Services\Kemdikbud\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background Job untuk sinkronisasi data ke API Kemdiktisaintek.
 *
 * Arsitektur (Implementation Plan v2):
 * - Menerima SyncQueue model (bukan record bisnis langsung)
 * - Update dual-layer status: sync_queue + tabel bisnis (status_internal)
 * - Error classification menentukan retry vs fail permanent
 * - Auto-pause queue pada auth failure
 *
 * Job ini TIDAK menggunakan Laravel retry mechanism ($tries/$backoff),
 * karena retry dikelola sendiri oleh SyncQueue model dan ProcessSyncQueue command.
 */
class SyncToKemdikbudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tidak menggunakan Laravel retry — retry dikelola oleh sync_queue table.
     */
    public int $tries = 1;

    /**
     * Timeout untuk job (45 detik — lebih dari HTTP timeout 30 detik).
     */
    public int $timeout = 45;

    public function __construct(
        private SyncQueue $syncQueueItem
    ) {}

    /**
     * Execute the job — kirim data ke Kemdikti.
     */
    public function handle(SyncService $syncService): void
    {
        $item = $this->syncQueueItem;
        $record = $item->syncable;

        if (!$record) {
            Log::error('SyncQueue item has no syncable record', ['sync_queue_id' => $item->id]);
            $item->markAsFailedPermanent(
                \App\Enums\SyncErrorCode::UNKNOWN_ERROR,
                'Data sumber tidak ditemukan (mungkin sudah dihapus).',
            );
            return;
        }

        // Tandai sebagai sedang diproses
        $item->markAsProcessing();

        try {
            // Rakit payload sesuai format Kemdikti
            $payload = $this->buildPayload($record, $item->getSyncType());

            // Kirim ke endpoint yang sesuai
            $response = match ($item->getSyncType()) {
                'prestasi' => $syncService->syncPrestasi($payload),
                'sertifikasi' => $syncService->syncSertifikasi($payload),
                'rekognisi' => $syncService->syncRekognisi($payload),
            };

            // Sukses! Update kedua layer status
            $kemdikbudId = $response['data']['id'] ?? 0;

            $item->markAsSuccess($kemdikbudId);
            $record->update([
                'status_internal' => StatusInternal::SYNC_SUCCESS,
                'pusat_kemdikbud_id' => $kemdikbudId,
            ]);

            Log::info('Sync ke Kemdikti berhasil', [
                'sync_queue_id' => $item->id,
                'type' => $item->getSyncType(),
                'record_id' => $record->id,
                'kemdikbud_id' => $kemdikbudId,
            ]);

        } catch (SyncAuthException $e) {
            // Auth failure → gagal permanen, queue sudah di-auto-pause oleh SyncService
            $item->markAsFailed($e->errorCode, $e->getMessage(), $e->errorDetail);
            $record->update(['status_internal' => StatusInternal::SYNC_FAILED]);

            Log::critical('Sync auth failure — queue auto-paused', [
                'sync_queue_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

        } catch (SyncValidationException $e) {
            // Validation error (422) → gagal permanen, jangan retry
            $item->markAsFailedPermanent($e->errorCode, $e->getMessage(), $e->errorDetail);
            $record->update(['status_internal' => StatusInternal::SYNC_FAILED]);

            Log::warning('Sync validation error — data perlu diperbaiki', [
                'sync_queue_id' => $item->id,
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

        } catch (SyncException $e) {
            // Server/Network/Rate limit error → cek retry
            $this->handleRetryableError($item, $record, $e);

        } catch (\Throwable $e) {
            // Unknown error catch-all
            $syncException = new SyncException(
                $e->getMessage(),
                \App\Enums\SyncErrorCode::UNKNOWN_ERROR,
                ['exception' => get_class($e), 'trace' => $e->getTraceAsString()],
            );
            $this->handleRetryableError($item, $record, $syncException);
        }
    }

    /**
     * Handle error yang bisa di-retry (SERVER_ERROR, NETWORK_ERROR).
     */
    private function handleRetryableError(SyncQueue $item, $record, SyncException $e): void
    {
        $currentAttempt = $item->attempt_count + 1;

        if ($currentAttempt >= $item->max_attempts) {
            // Sudah 3x gagal → gagal permanen, butuh manual retry
            $item->markAsFailed($e->errorCode, $e->getMessage(), $e->errorDetail);
            $record->update(['status_internal' => StatusInternal::SYNC_FAILED]);

            Log::error('Sync gagal setelah max attempts', [
                'sync_queue_id' => $item->id,
                'attempts' => $currentAttempt,
                'error' => $e->getMessage(),
            ]);
        } else {
            // Masih bisa retry → antrikan kembali dengan backoff
            $item->markAsRetryWaiting($e->errorCode, $e->getMessage(), $e->errorDetail);

            Log::warning('Sync gagal, akan retry', [
                'sync_queue_id' => $item->id,
                'attempt' => $currentAttempt,
                'next_retry' => $item->fresh()->next_retry_at,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rakit payload sesuai format yang diharapkan API Kemdikti.
     * Transformasi dari struktur flat DB Udinus → nested JSON Kemdikti.
     */
    private function buildPayload($record, string $type): array
    {
        // Reload relasi untuk memastikan data terbaru
        $record->load(['mahasiswa', 'dosen']);

        $base = [
            'level' => $record->level->value,
            'penyelenggara' => $record->penyelenggara,
            'url_peserta' => $record->url_peserta,
            'url_sertifikat' => $record->url_sertifikat,
            'tgl_sertifikat' => $record->tgl_sertifikat->format('Y-m-d'),
            'url_foto_upp' => $record->url_foto_upp,
            'url_dokumen_undangan' => $record->url_dokumen_undangan,
            'keterangan' => $record->keterangan ?? '',
            'mahasiswa' => $record->mahasiswa->map(fn($m) => [
                'nim' => $m->nim,
                'nama' => $m->nama,
            ])->toArray(),
            'dosen' => $record->dosen->map(fn($d) => [
                'nuptk' => $d->nuptk,
                'nama' => $d->nama,
                'url_surat_tugas' => $d->pivot->url_surat_tugas,
            ])->toArray(),
        ];

        // Field tambahan spesifik per tipe
        return match ($type) {
            'prestasi' => array_merge($base, [
                'kategori' => $record->kategori->value,
                'lomba' => $record->lomba,
                'cabang' => $record->cabang,
                'peringkat' => $record->peringkat->value,
                'jumlah_unit_peserta' => (string) $record->jumlah_unit_peserta,
                'kelompok_prestasi' => $record->kelompok_prestasi->value,
                'bentuk' => $record->bentuk->value,
            ]),
            'sertifikasi' => array_merge($base, [
                'nama' => $record->nama,
            ]),
            'rekognisi' => array_merge($base, [
                'nama' => $record->nama,
                'jenis' => $record->jenis->value,
            ]),
            default => $base,
        };
    }
}
