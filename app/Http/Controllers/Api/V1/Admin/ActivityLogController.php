<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogCollection;
use App\Http\Resources\ActivityLogResource;
use App\Services\ActivityLog\ActivityLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function index(Request $request): JsonResponse|ActivityLogCollection
    {
        $activities = $this->activityLogService->getAllLogs(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            causerId: $request->input('causer_id'),
            causerType: $request->input('causer_type'),
            event: $request->input('event'),
            modul: $request->input('modul'),
        );

        return new ActivityLogCollection($activities);
    }

    public function show(int $id): JsonResponse
    {
        $activity = $this->activityLogService->getLogDetail($id);

        if (!$activity) {
            return $this->errorResponse('Activity log tidak ditemukan.', 404);
        }

        return $this->successResponse(
            new ActivityLogResource($activity),
            'Detail activity log berhasil diambil.'
        );
    }
}
