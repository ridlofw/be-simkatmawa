<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sertifikasi\StoreSertifikasiRequest;
use App\Http\Resources\SertifikasiCollection;
use App\Http\Resources\SertifikasiResource;
use App\Services\Sertifikasi\SertifikasiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Sertifikasi — Endpoint Mahasiswa.
 * Thin Controller: delegasi logika bisnis ke SertifikasiService.
 */
class SertifikasiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SertifikasiService $sertifikasiService
    ) {}

    public function index(Request $request): JsonResponse|SertifikasiCollection
    {
        $user = $request->user();
        $nim = $user->mahasiswa?->nim;

        if (!$nim) {
            return $this->errorResponse('Data mahasiswa tidak ditemukan untuk akun ini.', 404);
        }

        $filters = $request->only(['status', 'level', 'search']);
        $sertifikasi = $this->sertifikasiService->getByMahasiswa($nim, $filters);

        return new SertifikasiCollection($sertifikasi);
    }

    public function store(StoreSertifikasiRequest $request): JsonResponse
    {
        $sertifikasi = $this->sertifikasiService->create($request->validated(), $request->user());

        return $this->createdResponse(
            new SertifikasiResource($sertifikasi),
            'Sertifikasi berhasil diajukan dan sedang menunggu verifikasi.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $sertifikasi = $this->sertifikasiService->findById($id);
        return $this->successResponse(new SertifikasiResource($sertifikasi), 'Detail sertifikasi berhasil diambil.');
    }

    public function update(StoreSertifikasiRequest $request, int $id): JsonResponse
    {
        $sertifikasi = $this->sertifikasiService->update($id, $request->validated(), $request->user());
        return $this->successResponse(new SertifikasiResource($sertifikasi), 'Data pengajuan berhasil diperbarui.');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $this->sertifikasiService->delete($id, $request->user());
        return $this->successResponse(null, 'Pengajuan berhasil dibatalkan dan dihapus.');
    }
}
