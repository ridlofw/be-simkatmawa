<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogCollection;
use App\Http\Resources\ActivityLogResource;
use App\Services\ActivityLog\ActivityLogService;
use App\Traits\ApiResponse;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Activity Log — Endpoint Mahasiswa.
 * Thin Controller: delegasi logika bisnis ke ActivityLogService.
 */
class ActivityLogController extends Controller
{
    use ApiResponse, HasPagination;

    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * [GET] Daftar riwayat aktivitas pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse|ActivityLogCollection
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $activities = $this->activityLogService->getUserLogs(
            $user,
            $this->getPaginationLimit($request->input('per_page')),
            $request->input('search')
        );

        return new ActivityLogCollection($activities);
    }

    /**
     * [GET] Detail riwayat aktivitas pengguna.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $activity = $this->activityLogService->getUserLogDetail($user, $id);

        if (!$activity) {
            return $this->errorResponse('Activity log tidak ditemukan.', 404);
        }

        return $this->successResponse(
            new ActivityLogResource($activity),
            'Detail activity log berhasil diambil.'
        );
    }
}
