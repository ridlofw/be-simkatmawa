<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Parsing Module Name dari Model (subject_type) atau log_name
        $moduleMap = [
            'App\Models\PrestasiMandiri' => 'Prestasi Mandiri',
            'App\Models\Sertifikasi' => 'Sertifikasi',
            'App\Models\Rekognisi' => 'Rekognisi',
            'App\Models\Mahasiswa' => 'Profil Mahasiswa',
            'App\Models\User' => 'Akun Pengguna',
        ];

        // Fallback ke log_name jika subject_type null (misal: aksi sync-queue)
        $logNameMap = [
            'sync-queue' => 'Sync Queue',
        ];

        $moduleName = $moduleMap[$this->subject_type]
            ?? $logNameMap[$this->log_name]
            ?? class_basename($this->subject_type ?? $this->log_name);

        // Parsing Target Anti-Kosong
        $target = '—';
        
        // 1. Coba ambil dari relasi (Untuk event created/updated)
        if ($this->subject) {
            $target = $this->subject->lomba ?? $this->subject->nama_sertifikasi ?? $this->subject->aktivitas ?? $this->subject->nama_kegiatan ?? $this->subject->judul ?? $this->subject->name ?? $this->subject->nama ?? $this->subject->key ?? $target;
        }

        // 2. Fallback baca JSON properties (Untuk event deleted atau jika subject null)
        if ($target === '—' && $this->properties) {
            $props = $this->properties->toArray();
            $attrs = $props['attributes'] ?? $props['old'] ?? [];
            
            $target = $attrs['lomba'] ?? $attrs['nama_sertifikasi'] ?? $attrs['aktivitas'] ?? $attrs['nama_kegiatan'] ?? $attrs['judul'] ?? $attrs['name'] ?? $attrs['nama'] ?? $attrs['key'] ?? $target;
        }

        // 3. Resolve target untuk log sync-queue (tidak punya subject relasi standar)
        if ($target === '—' && $this->log_name === 'sync-queue') {
            $props = $this->properties?->toArray() ?? [];

            $target = match ($this->event) {
                'pause', 'play', 'auto-pause' => 'Antrean Sinkronisasi',
                'retry' => 'Item #' . ($props['sync_queue_id'] ?? '?') . ' (' . (class_basename($props['syncable_type'] ?? '')) . ')',
                'retry-all' => ($props['retried_count'] ?? 0) . ' item gagal',
                default => $this->description ?? '—',
            };
        }

        // Format label aksi bahasa Indonesia
        $aksiLabel = match ($this->event) {
            'created' => 'Dibuat',
            'updated' => 'Diubah',
            'deleted' => 'Dihapus',
            'restored' => 'Dipulihkan',
            'retry' => 'Retry',
            'retry-all' => 'Retry Semua',
            'pause' => 'Pause',
            'play' => 'Resume',
            'auto-pause' => 'Auto-Pause',
            default => ucfirst($this->event),
        };

        return [
            'id' => $this->id,
            
            // Informasi Umum (Untuk Header Modal)
            'informasi_umum' => [
                'waktu' => $this->created_at?->translatedFormat('d M Y, H:i') . ' WIB',
                'aksi' => $aksiLabel,
                'pelaku' => $this->causer->name ?? 'Sistem',
                'role' => $this->causer ? (class_basename($this->causer) === 'Mahasiswa' ? 'mahasiswa' : 'admin') : 'sistem',
                'modul' => $moduleName,
                'target' => $target,
            ],

            // Perubahan Data (Untuk Tabel Before/After)
            'perubahan_data' => $this->resolvePerubahanData(),

            // Raw Data
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer_id' => $this->causer_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Resolve perubahan_data berdasarkan tipe log.
     * - CRUD logs: format standar old/attributes dari Spatie.
     * - Sync-queue logs: map properties ke format sebelum/sesudah yang bermakna.
     */
    private function resolvePerubahanData(): array
    {
        // Log CRUD standar (Spatie auto-log)
        if ($this->log_name !== 'sync-queue') {
            return [
                'sebelum' => $this->properties['old'] ?? null,
                'sesudah' => $this->properties['attributes'] ?? null,
            ];
        }

        // Log sync-queue: map properties ke format sebelum/sesudah
        $props = $this->properties?->toArray() ?? [];

        return match ($this->event) {
            'retry' => [
                'sebelum' => ['status' => $props['previous_status'] ?? null],
                'sesudah' => ['status' => 'pending'],
            ],
            'retry-all' => [
                'sebelum' => ['status' => 'failed', 'jumlah' => $props['retried_count'] ?? 0],
                'sesudah' => ['status' => 'pending', 'jumlah' => $props['retried_count'] ?? 0],
            ],
            'pause' => [
                'sebelum' => ['status_queue' => 'Aktif'],
                'sesudah' => ['status_queue' => 'Di-pause', 'alasan' => $props['reason'] ?? 'MANUAL'],
            ],
            'play' => [
                'sebelum' => ['status_queue' => 'Di-pause'],
                'sesudah' => ['status_queue' => 'Aktif'],
            ],
            'auto-pause' => [
                'sebelum' => ['status_queue' => 'Aktif'],
                'sesudah' => ['status_queue' => 'Di-pause', 'alasan' => $props['reason'] ?? '-', 'oleh' => 'SYSTEM'],
            ],
            default => [
                'sebelum' => null,
                'sesudah' => $props ?: null,
            ],
        };
    }
}
