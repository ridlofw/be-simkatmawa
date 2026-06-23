<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncQueueService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Sync Queue Monitoring — Admin.
 *
 * Admin dapat:
 * - Melihat statistik dan daftar antrean (stats, index)
 * - Retry single item / retry all failed
 *
 * Superadmin memiliki controller terpisah untuk play/pause dan error detail.
 */
class SyncQueueController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SyncQueueService $syncQueueService
    ) {}

    /**
     * [GET] Statistik sync queue untuk card dashboard.
     * Endpoint: /api/v1/admin/sync-queue/stats
     */
    public function stats(): JsonResponse
    {
        $stats = $this->syncQueueService->getStats();

        return $this->successResponse($stats, 'Statistik sync queue berhasil diambil.');
    }

    /**
     * [GET] Daftar antrean sinkronisasi (paginated, filterable).
     * Endpoint: /api/v1/admin/sync-queue
     *
     * Query params:
     * - status: all|menunggu|proses|berhasil|gagal (default: all)
     * - limit: items per page (default: 15)
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');
        $limit = (int) $request->query('limit', 15);

        $paginated = $this->syncQueueService->getQueue($status, $limit);

        // Transform data untuk frontend
        $items = collect($paginated->items())->map(function ($item) {
            return $this->transformQueueItem($item);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data sync queue berhasil diambil.',
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * [POST] Retry single item yang gagal.
     * Endpoint: /api/v1/admin/sync-queue/{id}/retry
     */
    public function retry(int $id): JsonResponse
    {
        $item = $this->syncQueueService->retry($id);

        if (!$item) {
            return $this->errorResponse(
                'Item tidak ditemukan atau tidak bisa di-retry (hanya item gagal yang bisa di-retry).',
                404
            );
        }

        return $this->successResponse(
            $this->transformQueueItem($item),
            'Item berhasil diantrikan ulang untuk retry.'
        );
    }

    /**
     * [POST] Retry semua item yang gagal (status 'failed', bukan 'failed_permanent').
     * Endpoint: /api/v1/admin/sync-queue/retry-all
     */
    public function retryAll(): JsonResponse
    {
        $count = $this->syncQueueService->retryAllFailed();

        return $this->successResponse(
            ['retried_count' => $count],
            "{$count} item gagal berhasil diantrikan ulang."
        );
    }

    /**
     * Transform SyncQueue model ke format response frontend.
     */
    private function transformQueueItem($item): array
    {
        $syncable = $item->syncable;

        // Resolve judul pengajuan
        $judul = match ($item->syncable_type) {
            'App\\Models\\PrestasiMandiri' => $syncable?->lomba ?? '-',
            'App\\Models\\Sertifikasi' => $syncable?->nama ?? '-',
            'App\\Models\\Rekognisi' => $syncable?->nama ?? '-',
            default => '-',
        };

        // Resolve mahasiswa (tampilkan pertama + count)
        $mahasiswaData = null;
        if ($syncable && $syncable->relationLoaded('mahasiswa')) {
            $mahasiswaList = $syncable->mahasiswa;
            if ($mahasiswaList->isNotEmpty()) {
                $first = $mahasiswaList->first();
                $mahasiswaData = [
                    'nama' => $first->nama,
                    'count' => $mahasiswaList->count(),
                ];
            }
        }

        return [
            'id' => $item->id,
            'pengajuan' => [
                'judul' => $judul,
                'kategori' => $item->getSyncType(),
                'kategori_label' => $item->getKategoriLabel(),
            ],
            'mahasiswa' => $mahasiswaData,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'attempt_count' => $item->attempt_count,
            'error_code' => $item->error_code?->value,
            'error_message' => $item->error_message,
            'queued_at' => $item->queued_at?->toIso8601String(),
            'started_at' => $item->started_at?->toIso8601String(),
            'completed_at' => $item->completed_at?->toIso8601String(),
        ];
    }
}
