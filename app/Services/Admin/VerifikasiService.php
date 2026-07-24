<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\Sync\SyncQueueService;
use App\Traits\ResolvesModelType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Service Layer — Verifikasi Admin (Kontrak_API_Frontend.md §D).
 * Menangani logika antrean, detail, dan proses approval/rejection pengajuan.
 */
class VerifikasiService
{
    use ResolvesModelType;

    public function __construct(
        private readonly SyncQueueService $syncQueueService
    ) {}

    /**
     * Ambil daftar antrean pengajuan (filterable by status).
     *
     * @return LengthAwarePaginator|null null jika tipe kegiatan tidak valid
     */
    public function getQueue(string $tipeKegiatan, string $status, int $limit): ?LengthAwarePaginator
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        // Load relasi mahasiswa
        $query = $modelClass::with('mahasiswa');

        // Filter status
        if ($status !== 'all') {
            // Mendukung pencarian banyak status sekaligus (dipisah koma) untuk halaman History
            if (str_contains($status, ',')) {
                $query->whereIn('status_internal', explode(',', $status));
            } else {
                $query->where('status_internal', $status);
            }
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Ambil detail pengajuan untuk review.
     *
     * @return Model|null null jika data tidak ditemukan
     */
    public function getDetail(string $tipeKegiatan, int $id): ?Model
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        return $modelClass::with(['mahasiswa', 'dosen'])->find($id);
    }

    /**
     * Cek apakah tipe kegiatan valid.
     */
    public function isValidType(string $tipeKegiatan): bool
    {
        return $this->resolveModelClass($tipeKegiatan) !== null;
    }

    /**
     * Proses verifikasi pengajuan — Approve atau Reject.
     *
     * @return Model|null null jika pengajuan tidak ditemukan
     */
    public function processVerification(
        string $tipeKegiatan,
        int $id,
        string $status,
        ?string $alasanPenolakan,
        User $admin,
        ?int $alasanPenolakanId = null
    ): ?Model {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        $pengajuan = $modelClass::find($id);

        if (!$pengajuan) {
            return null;
        }

        $adminId = $admin->id;
        $now = now();

        if ($status === 'APPROVE') {
            $pengajuan->update([
                'status_internal' => 'APPROVED_UNSYNCED',
                'alasan_penolakan' => null, // Reset alasan penolakan jika sebelumnya ditolak lalu disetujui ulang
                'approved_by' => $adminId,
                'approved_at' => $now,
            ]);

            // Masukkan ke antrean sinkronisasi (diproses oleh ProcessSyncQueue command)
            $this->syncQueueService->enqueue($pengajuan);

            // Notifikasi ke mahasiswa: "Pengajuan Disetujui"
            app(NotificationService::class)->submissionApproved($pengajuan);

        } elseif ($status === 'REJECT') {
            // Resolusi teks alasan penolakan jika alasanPenolakanId diberikan
            $finalReason = $alasanPenolakan;
            if ($alasanPenolakanId) {
                $masterReason = \App\Models\AlasanPenolakan::find($alasanPenolakanId);
                if ($masterReason) {
                    if (!empty($alasanPenolakan)) {
                        $finalReason = $masterReason->alasan . ' (Catatan Tambahan: ' . $alasanPenolakan . ')';
                    } else {
                        $finalReason = $masterReason->alasan;
                    }
                }
            }

            $pengajuan->update([
                'status_internal' => 'REJECTED',
                'alasan_penolakan' => $finalReason,
                'approved_by' => $adminId,
                'approved_at' => $now,
            ]);

            // Notifikasi ke mahasiswa: "Pengajuan Ditolak"
            app(NotificationService::class)->submissionRejected($pengajuan, $finalReason);
        }

        return $pengajuan;
    }
}
