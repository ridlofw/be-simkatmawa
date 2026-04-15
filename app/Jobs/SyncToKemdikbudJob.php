<?php

namespace App\Jobs;

use App\Enums\StatusInternal;
use App\Services\Kemdikbud\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background Job untuk sinkronisasi data ke API Kemdikbud Pusat.
 *
 * Arsitektur (Arsitektur_Backend.md §4 & System_Design §5):
 * - Dispatch saat Admin klik Approve
 * - Worker di background memproses antrean
 * - Auto-retry 3x dengan backoff 5 menit jika gagal
 */
class SyncToKemdikbudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah retry maksimal sebelum masuk ke failed_jobs.
     */
    public int $tries = 3;

    /**
     * Backoff strategy (detik): retry setelah 5 menit, 10 menit, 15 menit.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [300, 600, 900];
    }

    public function __construct(
        private Model $record,
        private string $type // 'prestasi', 'sertifikasi', 'rekognisi'
    ) {}

    /**
     * Execute the job — kirim data ke Kemdikbud.
     */
    public function handle(SyncService $syncService): void
    {
        try {
            // Rakit payload sesuai format Kemdikbud
            $payload = $this->buildPayload();

            // Kirim ke endpoint yang sesuai
            $response = match ($this->type) {
                'prestasi' => $syncService->syncPrestasi($payload),
                'sertifikasi' => $syncService->syncSertifikasi($payload),
                'rekognisi' => $syncService->syncRekognisi($payload),
                default => throw new \InvalidArgumentException("Tipe tidak valid: {$this->type}"),
            };

            // Sukses! Update status dan simpan ID dari Kemdikbud (PRD §3.5)
            $kemdikbudId = $response['data']['id'] ?? null;

            $this->record->update([
                'status_internal' => StatusInternal::SYNC_SUCCESS,
                'pusat_kemdikbud_id' => $kemdikbudId,
            ]);

            Log::info("Sync ke Kemdikbud berhasil", [
                'type' => $this->type,
                'record_id' => $this->record->id,
                'kemdikbud_id' => $kemdikbudId,
            ]);

        } catch (\Exception $e) {
            Log::error("Sync ke Kemdikbud gagal", [
                'type' => $this->type,
                'record_id' => $this->record->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update status ke SYNC_FAILED jika sudah retry terakhir
            if ($this->attempts() >= $this->tries) {
                $this->record->update([
                    'status_internal' => StatusInternal::SYNC_FAILED,
                ]);
            }

            throw $e; // Re-throw agar Laravel retry mechanism bekerja
        }
    }

    /**
     * Rakit payload sesuai format yang diharapkan API Kemdikbud.
     * Transformasi dari struktur flat DB Udinus → nested JSON Kemdikbud.
     */
    private function buildPayload(): array
    {
        // Reload relasi untuk memastikan data terbaru
        $this->record->load(['mahasiswa', 'dosen']);

        $base = [
            'level' => $this->record->level->value,
            'penyelenggara' => $this->record->penyelenggara,
            'url_peserta' => $this->record->url_peserta,
            'url_sertifikat' => $this->record->url_sertifikat,
            'tgl_sertifikat' => $this->record->tgl_sertifikat->format('Y-m-d'),
            'url_foto_upp' => $this->record->url_foto_upp,
            'url_dokumen_undangan' => $this->record->url_dokumen_undangan,
            'keterangan' => $this->record->keterangan ?? '',
            'mahasiswa' => $this->record->mahasiswa->map(fn($m) => [
                'nim' => $m->nim,
                'nama' => $m->nama,
            ])->toArray(),
            'dosen' => $this->record->dosen->map(fn($d) => [
                'nuptk' => $d->nuptk,
                'nama' => $d->nama,
                'url_surat_tugas' => $d->pivot->url_surat_tugas,
            ])->toArray(),
        ];

        // Field tambahan spesifik per tipe
        return match ($this->type) {
            'prestasi' => array_merge($base, [
                'kategori' => $this->record->kategori->value,
                'lomba' => $this->record->lomba,
                'cabang' => $this->record->cabang,
                'peringkat' => $this->record->peringkat->value,
                'jumlah_unit_peserta' => (string) $this->record->jumlah_unit_peserta,
                'kelompok_prestasi' => $this->record->kelompok_prestasi->value,
                'bentuk' => $this->record->bentuk->value,
            ]),
            'sertifikasi' => array_merge($base, [
                'nama' => $this->record->nama,
            ]),
            'rekognisi' => array_merge($base, [
                'nama' => $this->record->nama,
                'jenis' => $this->record->jenis->value,
            ]),
            default => $base,
        };
    }
}
