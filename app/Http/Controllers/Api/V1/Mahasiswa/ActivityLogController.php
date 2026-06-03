<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogCollection;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Controller Activity Log — Endpoint Mahasiswa.
 */
class ActivityLogController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Daftar riwayat aktivitas pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse|ActivityLogCollection
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = Activity::with('causer')
            ->where('causer_id', $user->id)
            ->where('causer_type', get_class($user))
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate($perPage);

        return new ActivityLogCollection($activities);
    }

    /**
     * [GET] Detail riwayat aktivitas pengguna.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        
        $activity = Activity::with(['causer', 'subject'])
            ->where('causer_id', $user->id)
            ->findOrFail($id);

        return $this->successResponse(
            new \App\Http\Resources\ActivityLogResource($activity),
            'Detail activity log berhasil diambil.'
        );
    }
}
