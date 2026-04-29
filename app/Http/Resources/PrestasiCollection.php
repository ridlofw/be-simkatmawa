<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * ResourceCollection untuk daftar Prestasi Mandiri (paginated).
 * Membungkus response list + pagination metadata sesuai kontrak API.
 */
class PrestasiCollection extends ResourceCollection
{
    /**
     * Gunakan PrestasiResource untuk setiap item di dalam collection.
     *
     * @var string
     */
    public $collects = PrestasiResource::class;

    /**
     * Transform the resource collection into an array.
     * Laravel otomatis menyertakan pagination metadata (current_page, last_page, total).
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    /**
     * Wrap response dengan format kontrak standar:
     * { "success": true, "message": "...", "data": { paginated... }, "errors": null }
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Riwayat pengajuan prestasi berhasil diambil.',
            'errors' => null,
        ];
    }
}
