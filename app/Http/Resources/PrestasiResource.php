<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource untuk Prestasi Mandiri (single object).
 * Transformasi data model + relasi pivot ke format JSON Frontend.
 */
class PrestasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Field Kemdikbud
            'level' => $this->level?->value,
            'kategori' => $this->kategori?->value,
            'lomba' => $this->lomba,
            'cabang' => $this->cabang,
            'penyelenggara' => $this->penyelenggara,
            'peringkat' => $this->peringkat?->value,
            'jumlah_unit_peserta' => $this->jumlah_unit_peserta,
            'kelompok_prestasi' => $this->kelompok_prestasi?->value,
            'bentuk' => $this->bentuk?->value,
            'url_peserta' => $this->url_peserta,
            'url_sertifikat' => $this->url_sertifikat,
            'tgl_sertifikat' => $this->tgl_sertifikat,
            'url_foto_upp' => $this->url_foto_upp,
            'url_dokumen_undangan' => $this->url_dokumen_undangan,
            'keterangan' => $this->keterangan,

            // Field Internal Udinus
            'status_internal' => $this->status_internal?->value,
            'alasan_penolakan' => $this->alasan_penolakan,
            'pusat_kemdikbud_id' => $this->pusat_kemdikbud_id,

            // Relasi Pivot — Mahasiswa peserta
            'mahasiswa' => $this->whenLoaded('mahasiswa', function () {
                return $this->mahasiswa->map(fn($mhs) => [
                    'nim' => $mhs->nim,
                    'nama' => $mhs->nama,
                ]);
            }),

            // Relasi Pivot — Dosen pembimbing
            'dosen' => $this->whenLoaded('dosen', function () {
                return $this->dosen->map(fn($dsn) => [
                    'nuptk' => $dsn->nuptk,
                    'nama' => $dsn->nama,
                    'url_surat_tugas' => $dsn->pivot->url_surat_tugas,
                ]);
            }),

            // Audit Trail
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'approved_by' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ] : null;
            }),
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
