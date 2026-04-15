<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource untuk transformasi data Prestasi Mandiri ke Frontend.
 * Memastikan field sensitif tersembunyi dan struktur JSON konsisten.
 */
class PrestasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'kategori' => $this->kategori,
            'lomba' => $this->lomba,
            'cabang' => $this->cabang,
            'penyelenggara' => $this->penyelenggara,
            'peringkat' => $this->peringkat,
            'jumlah_unit_peserta' => $this->jumlah_unit_peserta,
            'kelompok_prestasi' => $this->kelompok_prestasi,
            'bentuk' => $this->bentuk,
            'url_peserta' => $this->url_peserta,
            'url_sertifikat' => $this->url_sertifikat,
            'tgl_sertifikat' => $this->tgl_sertifikat?->format('Y-m-d'),
            'url_foto_upp' => $this->url_foto_upp,
            'url_dokumen_undangan' => $this->url_dokumen_undangan,
            'keterangan' => $this->keterangan,
            'status_internal' => $this->status_internal,
            'alasan_penolakan' => $this->alasan_penolakan,
            'mahasiswa' => $this->whenLoaded('mahasiswa', fn() =>
                $this->mahasiswa->map(fn($m) => [
                    'nim' => $m->nim,
                    'nama' => $m->nama,
                ])
            ),
            'dosen' => $this->whenLoaded('dosen', fn() =>
                $this->dosen->map(fn($d) => [
                    'nuptk' => $d->nuptk,
                    'nama' => $d->nama,
                    'url_surat_tugas' => $d->pivot->url_surat_tugas,
                ])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
