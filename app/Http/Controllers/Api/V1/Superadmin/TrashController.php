<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Recycle Bin Superadmin (Kontrak_API_Frontend.md §E.15-16).
 * Akses ke data yang telah di-soft delete.
 */
class TrashController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Daftar data terhapus (Recycle Bin).
     */
    public function index(string $tipeKegiatan): JsonResponse
    {
        // TODO: Implementasi — onlyTrashed() sesuai $tipeKegiatan
        return $this->successResponse([], 'Data recycle bin berhasil ditarik.');
    }

    /**
     * [PUT] Pulihkan data dari Recycle Bin (Restore).
     */
    public function restore(string $tipeKegiatan, int $id): JsonResponse
    {
        // TODO: Implementasi — restore()
        return $this->successResponse(null, 'Data berhasil dipulihkan dari Recycle Bin.');
    }

    /**
     * [DELETE] Force delete data dari Recycle Bin (pemusnahan mutlak).
     */
    public function forceDelete(string $tipeKegiatan, int $id): JsonResponse
    {
        // TODO: Implementasi — forceDelete() + cek status != SYNC_SUCCESS
        return $this->successResponse(null, 'Data berhasil dihapus permanen.');
    }
}
