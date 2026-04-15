<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Sertifikasi — Endpoint Mahasiswa.
 */
class SertifikasiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        // TODO: Implementasi via SertifikasiService
        return $this->successResponse([], 'Riwayat pengajuan sertifikasi berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Implementasi via SertifikasiService + StoreSertifikasiRequest
        return $this->createdResponse(null, 'Sertifikasi berhasil diajukan dan sedang menunggu verifikasi.');
    }

    public function show(int $id): JsonResponse
    {
        // TODO: Implementasi via SertifikasiService
        return $this->successResponse(null, 'Detail sertifikasi berhasil diambil.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: Implementasi via SertifikasiService
        return $this->successResponse(null, 'Data pengajuan berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: Implementasi via SertifikasiService
        return $this->successResponse(null, 'Pengajuan berhasil dibatalkan dan dihapus.');
    }
}
