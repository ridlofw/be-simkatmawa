<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Superadmin\TrashService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TrashService $trashService
    ) {}

    /**
     * [GET] List data di recycle bin
     */
    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        if (!$this->trashService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $limit = $request->query('limit', config('pagination.per_page'));
        $search = $request->query('search');
        $status = $request->query('status'); // untuk filter status_internal jika ada

        $result = $this->trashService->getTrashedItems($tipeKegiatan, $limit, $search, $status);

        $paginated = $result['paginated'];

        return response()->json([
            'success' => true,
            'message' => "Data keranjang sampah $tipeKegiatan berhasil ditarik.",
            'data'    => $result['data'],
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'stats'   => [
                "total_trash_{$tipeKegiatan}" => $result['totalTrash'],
            ]
        ]);
    }

    /**
     * [GET] Detail data di recycle bin
     */
    public function show(string $tipeKegiatan, string $id): JsonResponse
    {
        if (!$this->trashService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $data = $this->trashService->getTrashedDetail($tipeKegiatan, $id);

        if (!$data) {
            return $this->errorResponse("Data tidak ditemukan di keranjang sampah.", 404);
        }

        return $this->successResponse($data, 'Detail data keranjang sampah berhasil diambil.');
    }

    /**
     * [PUT] Memulihkan data (Restore)
     */
    public function restore(string $tipeKegiatan, string $id): JsonResponse
    {
        if (!$this->trashService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $restored = $this->trashService->restoreItem($tipeKegiatan, $id);

        if ($restored === false) {
            return $this->errorResponse("Data tidak ditemukan di keranjang sampah.", 404);
        }

        return $this->successResponse(null, "Data $tipeKegiatan berhasil dipulihkan.");
    }
}
