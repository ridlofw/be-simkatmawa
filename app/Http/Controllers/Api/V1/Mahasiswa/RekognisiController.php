<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rekognisi\StoreRekognisiRequest;
use App\Http\Resources\RekognisiCollection;
use App\Http\Resources\RekognisiResource;
use App\Services\Rekognisi\RekognisiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Rekognisi — Endpoint Mahasiswa.
 * Thin Controller: delegasi logika bisnis ke RekognisiService.
 */
class RekognisiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RekognisiService $rekognisiService
    ) {}

    public function index(Request $request): JsonResponse|RekognisiCollection
    {
        $user = $request->user();
        $nim = $user->mahasiswa?->nim;

        if (!$nim) {
            return $this->errorResponse('Data mahasiswa tidak ditemukan untuk akun ini.', 404);
        }

        $filters = $request->only(['status', 'level', 'search', 'jenis_group']);
        $rekognisi = $this->rekognisiService->getByMahasiswa($nim, $filters);

        return new RekognisiCollection($rekognisi);
    }

    public function store(StoreRekognisiRequest $request): JsonResponse
    {
        $rekognisi = $this->rekognisiService->create($request->validated(), $request->user());

        return $this->createdResponse(
            new RekognisiResource($rekognisi),
            'Rekognisi berhasil diajukan dan sedang menunggu verifikasi.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $rekognisi = $this->rekognisiService->findById($id);
        return $this->successResponse(new RekognisiResource($rekognisi), 'Detail rekognisi berhasil diambil.');
    }

    public function update(StoreRekognisiRequest $request, int $id): JsonResponse
    {
        $rekognisi = $this->rekognisiService->update($id, $request->validated(), $request->user());
        return $this->successResponse(new RekognisiResource($rekognisi), 'Data pengajuan berhasil diperbarui.');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $this->rekognisiService->delete($id, $request->user());
        return $this->successResponse(null, 'Pengajuan berhasil dibatalkan dan dihapus.');
    }
}
