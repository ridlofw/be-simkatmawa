<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Rekognisi — Endpoint Mahasiswa.
 */
class RekognisiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        // TODO: Implementasi via RekognisiService
        return $this->successResponse([], 'Riwayat pengajuan rekognisi berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Implementasi via RekognisiService + StoreRekognisiRequest
        return $this->createdResponse(null, 'Rekognisi berhasil diajukan dan sedang menunggu verifikasi.');
    }

    public function show(int $id): JsonResponse
    {
        // TODO: Implementasi via RekognisiService
        return $this->successResponse(null, 'Detail rekognisi berhasil diambil.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: Implementasi via RekognisiService
        return $this->successResponse(null, 'Data pengajuan berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: Implementasi via RekognisiService
        return $this->successResponse(null, 'Pengajuan berhasil dibatalkan dan dihapus.');
    }
}
