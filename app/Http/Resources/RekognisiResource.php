<?php

namespace App\Http\Resources;

use App\Enums\StatusInternal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource — Rekognisi.
 *
 * Kolom tabel frontend:
 * ID | Nama Rekognisi (nama, penyelenggara) | Jenis | Level |
 * Tahun (dari tgl_sertifikat) | Status | Aksi
 */
class RekognisiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user && $this->created_by === $user->id;
        $statusValue = $this->status_internal?->value ?? $this->status_internal;
        $editableStatuses = [StatusInternal::PENDING->value, StatusInternal::REJECTED->value];

        return [
            'id' => $this->id,

            // Data Kegiatan
            'level' => $this->level?->value,
            'jenis' => $this->jenis?->value,
            'nama' => $this->nama,
            'penyelenggara' => $this->penyelenggara,
            'url_peserta' => $this->url_peserta,
            'url_sertifikat' => $this->url_sertifikat,
            'tgl_sertifikat' => $this->tgl_sertifikat?->format('Y-m-d'),
            'url_foto_upp' => $this->url_foto_upp,
            'url_dokumen_undangan' => $this->url_dokumen_undangan,
            'keterangan' => $this->keterangan,

            // Computed: Tahun
            'tahun' => $this->tgl_sertifikat?->format('Y'),

            // Status + Komentar Admin
            'status_internal' => $statusValue,
            'alasan_penolakan' => $this->alasan_penolakan,
            'pusat_kemdikbud_id' => $this->pusat_kemdikbud_id,

            // Relasi Pivot — Mahasiswa (diurutkan: urutan 0 = Ketua)
            'mahasiswa' => $this->whenLoaded('mahasiswa', fn() =>
                $this->mahasiswa->map(fn($m) => [
                    'nim' => $m->nim,
                    'nama' => $m->nama,
                    'urutan' => $m->pivot->urutan,
                ])
            ),

            // Relasi Pivot — Dosen
            'dosen' => $this->whenLoaded('dosen', fn() =>
                $this->dosen->map(fn($d) => [
                    'nuptk' => $d->nuptk,
                    'nama' => $d->nama,
                    'url_surat_tugas' => $d->pivot->url_surat_tugas,
                ])
            ),

            // Audit Trail
            'created_by' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed: Hak aksi
            'can_edit' => $isOwner && in_array($statusValue, $editableStatuses),
            'can_delete' => $isOwner && in_array($statusValue, $editableStatuses),
        ];
    }
}
