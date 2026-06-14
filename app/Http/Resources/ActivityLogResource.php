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

        // Parsing Target (Ambil dari properties jika memungkinkan, atau dari subject)
        $target = '-';
        if ($this->properties && isset($this->properties['attributes'])) {
            $attrs = $this->properties['attributes'];
            // Prioritas penamaan target berdasarkan kolom yang biasanya ada
            $target = $attrs['lomba'] ?? $attrs['nama_kegiatan'] ?? $attrs['judul'] ?? $attrs['nama_sertifikasi'] ?? $attrs['nama'] ?? $target;
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
                'pelaku' => $this->whenLoaded('causer', fn() => $this->causer->name, 'Sistem'),
                'role' => $this->whenLoaded('causer', function() {
                    return class_basename($this->causer) === 'Mahasiswa' ? 'mahasiswa' : 'admin';
                }, 'sistem'),
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
