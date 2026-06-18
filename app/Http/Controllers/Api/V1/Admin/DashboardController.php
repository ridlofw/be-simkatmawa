<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->dashboardService->getDashboardData();

        return response()->json([
            'success' => true,
            'message' => 'Data ringkasan dashboard berhasil ditarik secara aktual.',
            'data' => $data,
        ]);
    }
}
