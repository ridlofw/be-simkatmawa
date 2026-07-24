<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Superadmin\AlasanPenolakanService;
use App\Traits\ApiResponse;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlasanPenolakanController extends Controller
{
    use ApiResponse, HasPagination;

    public function __construct(
        private readonly AlasanPenolakanService $alasanPenolakanService
    ) {}

    /**
     * [GET] List master alasan penolakan dengan paginasi & search.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $this->getPaginationLimit($request->query('limit'));
        $search = $request->query('search');
        $isActiveParam = $request->query('is_active');
        $isActive = $isActiveParam !== null ? filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN) : null;

        $paginated = $this->alasanPenolakanService->listReasons($limit, $search, $isActive);

        return response()->json([
            'success' => true,
            'message' => 'Data master alasan penolakan berhasil ditarik.',
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }

    /**
     * [GET] Detail master alasan penolakan.
     */
    public function show(int $id): JsonResponse
    {
        $reason = $this->alasanPenolakanService->getDetail($id);

        if (!$reason) {
            return $this->errorResponse('Master alasan penolakan tidak ditemukan.', 404);
        }

        return $this->successResponse($reason, 'Detail master alasan penolakan berhasil diambil.');
    }

    /**
     * [POST] Buat master alasan penolakan baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'alasan' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $reason = $this->alasanPenolakanService->createReason($validated, $request->user()->id);

        return $this->successResponse($reason, 'Master alasan penolakan berhasil ditambahkan.', 201);
    }

    /**
     * [PUT] Update master alasan penolakan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'sometimes|required|string|max:255',
            'alasan' => 'sometimes|required|string',
            'is_active' => 'sometimes|required|boolean',
        ]);

        $reason = $this->alasanPenolakanService->updateReason($id, $validated, $request->user()->id);

        if (!$reason) {
            return $this->errorResponse('Master alasan penolakan tidak ditemukan.', 404);
        }

        return $this->successResponse($reason, 'Master alasan penolakan berhasil diperbarui.');
    }

    /**
     * [DELETE] Hapus (soft delete) master alasan penolakan.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $this->alasanPenolakanService->deleteReason($id, $request->user()->id);

        if (!$deleted) {
            return $this->errorResponse('Master alasan penolakan tidak ditemukan.', 404);
        }

        return $this->successResponse(null, 'Master alasan penolakan berhasil dihapus.');
    }
}
