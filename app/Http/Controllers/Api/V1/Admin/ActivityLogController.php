<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogCollection;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse|ActivityLogCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        
        // Filter opsional berdasarkan causer_type / causer_id
        $causerId = $request->input('causer_id');
        $causerType = $request->input('causer_type'); 
        
        // Filter opsional berdasarkan event (created, updated, deleted)
        $event = $request->input('event');
        
        // Filter opsional berdasarkan modul (prestasi, sertifikasi, rekognisi, dll)
        $modul = $request->input('modul');

        $query = Activity::with(['causer', 'subject'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }
        
        if ($causerId) {
            $query->where('causer_id', $causerId);
        }
        
        if ($causerType) {
            $query->where('causer_type', $causerType);
        }
        
        if ($event) {
            $query->where('event', $event);
        }
        
        if ($modul) {
            $subjectMap = [
                'prestasi' => 'App\Models\PrestasiMandiri',
                'sertifikasi' => 'App\Models\Sertifikasi',
                'rekognisi' => 'App\Models\Rekognisi',
            ];
            
            if (isset($subjectMap[strtolower($modul)])) {
                $query->where('subject_type', $subjectMap[strtolower($modul)]);
            } else {
                // Bisa untuk mem-filter modul lain jika dikirimkan Full Class Path
                $query->where('subject_type', $modul);
            }
        }

        $activities = $query->paginate($perPage);

        return new ActivityLogCollection($activities);
    }

    public function show(int $id): JsonResponse
    {
        $activity = Activity::with(['causer', 'subject'])->findOrFail($id);

        return $this->successResponse(
            new \App\Http\Resources\ActivityLogResource($activity),
            'Detail activity log berhasil diambil.'
        );
    }
}
