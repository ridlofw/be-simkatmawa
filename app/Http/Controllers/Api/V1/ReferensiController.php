<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReferensiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Referensi (Kontrak_API_Frontend.md §B).
 * Thin Controller — delegasi logika ke ReferensiService.
 */
class ReferensiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReferensiService $referensiService
    ) {}

    /**
     * [GET] Referensi Enums — Data dropdown untuk form Frontend.
     */
    public function enums(): JsonResponse
    {
        $data = $this->referensiService->getEnums();

        return $this->successResponse($data, 'Data referensi berhasil diambil.');
    }

    /**
     * [GET] Lookup Mahasiswa — Cari berdasarkan Keyword Nama.
     * Digunakan Frontend untuk fitur dropdown search (?q=...).
     */
    public function searchMahasiswa(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string']);

        $mahasiswa = $this->referensiService->searchMahasiswa($request->input('q'));

        return $this->successResponse($mahasiswa, 'Data mahasiswa berhasil diambil.');
    }

    /**
     * [GET] Lookup Dosen — Cari berdasarkan Keyword Nama.
     * Digunakan Frontend untuk fitur dropdown search (?q=...).
     */
    public function searchDosen(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string']);

        $dosen = $this->referensiService->searchDosen($request->input('q'));

        return $this->successResponse($dosen, 'Data dosen berhasil diambil.');
    }

    /**
     * [GET] Referensi Alasan Penolakan — List master alasan penolakan yang aktif.
     */
    public function alasanPenolakan(): JsonResponse
    {
        $reasons = $this->referensiService->getAlasanPenolakan();

        return $this->successResponse($reasons, 'Data referensi alasan penolakan berhasil diambil.');
    }
}
