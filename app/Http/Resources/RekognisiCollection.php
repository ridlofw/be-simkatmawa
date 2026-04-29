<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * ResourceCollection untuk daftar Rekognisi (paginated).
 */
class RekognisiCollection extends ResourceCollection
{
    public $collects = RekognisiResource::class;

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Riwayat pengajuan rekognisi berhasil diambil.',
            'errors' => null,
        ];
    }
}
