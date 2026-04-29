<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * ResourceCollection untuk daftar Sertifikasi (paginated).
 */
class SertifikasiCollection extends ResourceCollection
{
    public $collects = SertifikasiResource::class;

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Riwayat pengajuan sertifikasi berhasil diambil.',
            'errors' => null,
        ];
    }
}
