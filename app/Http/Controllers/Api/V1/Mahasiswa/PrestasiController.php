<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestasi\StorePrestasiRequest;
use App\Http\Resources\PrestasiCollection;
use App\Http\Resources\PrestasiResource;
use App\Services\Prestasi\PrestasiService;
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

    public function __construct(
        private readonly PrestasiService $prestasiService
    ) {}

    /**
     * [GET] Daftar riwayat pengajuan prestasi mahasiswa.
     * Scope: semua prestasi yang NIM mahasiswa login ada di pivot.
     */
    public function index(Request $request): JsonResponse|PrestasiCollection
    {
        $user = $request->user();
        $nim = $user->mahasiswa?->nim;

        if (!$nim) {
            return $this->errorResponse('Data mahasiswa tidak ditemukan untuk akun ini.', 404);
        }

        $filters = $request->only(['status', 'level', 'search']);
        $prestasi = $this->prestasiService->getByMahasiswa($nim, $filters);

        return new PrestasiCollection($prestasi);
    }

    /**
     * [POST] Buat pengajuan prestasi mandiri baru.
     */
    public function store(StorePrestasiRequest $request): JsonResponse
    {
        $prestasi = $this->prestasiService->create(
            $request->validated(),
            $request->user()
        );

        return $this->createdResponse(
            new PrestasiResource($prestasi),
            'Prestasi berhasil diajukan dan sedang menunggu verifikasi.'
        );
    }

    /**
     * [GET] Detail pengajuan prestasi.
     */
    public function show(int $id): JsonResponse
    {
        $prestasi = $this->prestasiService->findById($id);

        return $this->successResponse(
            new PrestasiResource($prestasi),
            'Detail prestasi berhasil diambil.'
        );
    }

    /**
     * [PUT] Edit pengajuan prestasi (hanya jika PENDING/REJECTED + created_by).
     */
    public function update(StorePrestasiRequest $request, int $id): JsonResponse
    {
        $prestasi = $this->prestasiService->update(
            $id,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(
            new PrestasiResource($prestasi),
            'Data pengajuan berhasil diperbarui.'
        );
    }

    /**
     * [DELETE] Soft delete pengajuan prestasi (hanya jika PENDING + created_by).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $this->prestasiService->delete($id, $request->user());

        return $this->successResponse(null, 'Pengajuan berhasil dibatalkan dan dihapus.');
    }
}
