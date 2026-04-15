<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Prestasi Mandiri — Endpoint Mahasiswa (Kontrak_API_Frontend.md §C).
 * Thin Controller: delegasi logika bisnis ke PrestasiService.
 */
class PrestasiController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Daftar riwayat pengajuan prestasi mahasiswa.
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implementasi via PrestasiService
        return $this->successResponse([], 'Riwayat pengajuan prestasi berhasil diambil.');
    }

    /**
     * [POST] Buat pengajuan prestasi mandiri baru.
     */
    public function store(Request $request): JsonResponse
    {
        // TODO: Implementasi via PrestasiService + StorePrestasiRequest
        return $this->createdResponse(null, 'Prestasi berhasil diajukan dan sedang menunggu verifikasi.');
    }

    /**
     * [GET] Detail pengajuan prestasi.
     */
    public function show(int $id): JsonResponse
    {
        // TODO: Implementasi via PrestasiService
        return $this->successResponse(null, 'Detail prestasi berhasil diambil.');
    }

    /**
     * [PUT] Edit pengajuan prestasi (hanya jika PENDING/REJECTED).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: Implementasi via PrestasiService + state check
        return $this->successResponse(null, 'Data pengajuan berhasil diperbarui.');
    }

    /**
     * [DELETE] Soft delete pengajuan prestasi (hanya jika PENDING).
     */
    public function destroy(int $id): JsonResponse
    {
        // TODO: Implementasi via PrestasiService + state check
        return $this->successResponse(null, 'Pengajuan berhasil dibatalkan dan dihapus.');
    }
}
