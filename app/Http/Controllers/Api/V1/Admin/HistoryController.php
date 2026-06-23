<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\HistoryService;
use App\Traits\ApiResponse;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    use ApiResponse, HasPagination;

    public function __construct(
        private readonly HistoryService $historyService
    ) {}

    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        if (!$this->historyService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $limit = $this->getPaginationLimit($request->query('limit'));
        $status = $request->query('status');
        $search = $request->query('search');

        $paginated = $this->historyService->getHistory($tipeKegiatan, $limit, $status, $search);

        return response()->json([
            'success' => true,
            'message' => "Data history $tipeKegiatan berhasil ditarik.",
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }

    public function show(string $tipeKegiatan, int $id): JsonResponse
    {
        if (!$this->historyService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $data = $this->historyService->getHistoryDetail($tipeKegiatan, $id);

        if (!$data) {
            return $this->errorResponse("Data history tidak ditemukan.", 404);
        }

        return $this->successResponse($data, 'Detail history berhasil diambil.');
    }
}
