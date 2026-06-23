<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncQueueService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Sync Queue — Superadmin Only.
 *
 * Superadmin memiliki akses eksklusif untuk:
 * - Play/Pause toggle queue
 * - Melihat error detail lengkap (full response body Kemdikti)
 */
class SyncQueueController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SyncQueueService $syncQueueService
    ) {}

    /**
     * [POST] Toggle play/pause sync queue.
     * Endpoint: /api/v1/superadmin/sync-queue/toggle
     */
    public function toggle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:play,pause',
        ], [
            'action.required' => 'Action wajib diisi.',
            'action.in' => 'Action harus play atau pause.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $action = $request->input('action');
        $result = $this->syncQueueService->togglePause($action, $request->user()?->id);

        $message = $action === 'pause'
            ? 'Sync queue berhasil di-pause. Tidak ada item yang akan diproses sampai di-resume.'
            : 'Sync queue berhasil di-resume. Item akan mulai diproses kembali.';

        return $this->successResponse($result, $message);
    }

    /**
     * [GET] Detail item queue termasuk full error detail (untuk debugging).
     * Endpoint: /api/v1/superadmin/sync-queue/{id}
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->syncQueueService->getDetail($id);

        if (!$item) {
            return $this->notFoundResponse('Item sync queue tidak ditemukan.');
        }

        $syncable = $item->syncable;

        // Resolve judul pengajuan
        $judul = match ($item->syncable_type) {
            'App\\Models\\PrestasiMandiri' => $syncable?->lomba ?? '-',
            'App\\Models\\Sertifikasi' => $syncable?->nama ?? '-',
            'App\\Models\\Rekognisi' => $syncable?->nama ?? '-',
            default => '-',
        };

        $data = [
            'id' => $item->id,
            'pengajuan' => [
                'judul' => $judul,
                'kategori' => $item->getSyncType(),
                'kategori_label' => $item->getKategoriLabel(),
                'record_id' => $item->syncable_id,
            ],
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'attempt_count' => $item->attempt_count,
            'max_attempts' => $item->max_attempts,
            'error_code' => $item->error_code?->value,
            'error_message' => $item->error_message,
            'error_detail' => $item->error_detail, // Full detail hanya untuk Superadmin
            'kemdikbud_id' => $item->kemdikbud_id,
            'queued_at' => $item->queued_at?->toIso8601String(),
            'started_at' => $item->started_at?->toIso8601String(),
            'completed_at' => $item->completed_at?->toIso8601String(),
            'next_retry_at' => $item->next_retry_at?->toIso8601String(),
            // Relasi data pengajuan lengkap
            'syncable' => $syncable,
        ];

        return $this->successResponse($data, 'Detail sync queue berhasil diambil.');
    }
}
