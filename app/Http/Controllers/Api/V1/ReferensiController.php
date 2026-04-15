<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Bentuk;
use App\Enums\JenisRekognisi;
use App\Enums\KategoriPrestasi;
use App\Enums\KelompokPrestasi;
use App\Enums\Level;
use App\Enums\Peringkat;
use App\Enums\StatusInternal;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Controller Referensi Enum (Kontrak_API_Frontend.md §B).
 * Menyediakan kamus data dropdown agar Frontend tidak perlu hardcode.
 */
class ReferensiController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Referensi Enums — Data dropdown untuk form Frontend.
     */
    public function enums(): JsonResponse
    {
        return $this->successResponse([
            'level' => array_column(Level::cases(), 'value'),
            'kategori_prestasi' => array_column(KategoriPrestasi::cases(), 'value'),
            'peringkat' => array_column(Peringkat::cases(), 'value'),
            'kelompok_prestasi' => array_column(KelompokPrestasi::cases(), 'value'),
            'bentuk' => array_column(Bentuk::cases(), 'value'),
            'jenis_rekognisi' => array_column(JenisRekognisi::cases(), 'value'),
            'status_internal' => array_column(StatusInternal::cases(), 'value'),
        ], 'Data referensi berhasil diambil.');
    }
}
