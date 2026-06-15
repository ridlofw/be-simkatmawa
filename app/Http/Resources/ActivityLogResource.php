<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Parsing Module Name dari Model
        $moduleMap = [
            'App\Models\PrestasiMandiri' => 'Prestasi Mandiri',
            'App\Models\Sertifikasi' => 'Sertifikasi',
            'App\Models\Rekognisi' => 'Rekognisi',
            'App\Models\Mahasiswa' => 'Profil Mahasiswa',
            'App\Models\User' => 'Akun Pengguna',
        ];
        $moduleName = $moduleMap[$this->subject_type] ?? class_basename($this->subject_type);

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

        // Format label aksi bahasa Indonesia
        $aksiLabel = match ($this->event) {
            'created' => 'Dibuat',
            'updated' => 'Diubah',
            'deleted' => 'Dihapus',
            'restored' => 'Dipulihkan',
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
            'perubahan_data' => [
                'sebelum' => $this->properties['old'] ?? null,
                'sesudah' => $this->properties['attributes'] ?? null,
            ],

            // Raw Data
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer_id' => $this->causer_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
