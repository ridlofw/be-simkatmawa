<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\Rekognisi\RekognisiService;
use App\Services\Sync\SyncQueueService;
use App\Traits\HasFilterSort;
use App\Traits\HasPagination;
use App\Traits\ResolvesModelType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Service Layer — Verifikasi Admin (Unified Daftar Prestasi).
 * Menangani logika listing (filter/sort/search), detail, dan proses approval/rejection.
 */
class VerifikasiService
{
    use ResolvesModelType, HasFilterSort, HasPagination;

    public function __construct(
        private readonly SyncQueueService $syncQueueService
    ) {}

    /**
     * Konfigurasi filter & sort per tipe kegiatan.
     * Mendefinisikan kolom yang boleh di-sort, di-filter, dan kolom search utama.
     */
    private function getFilterConfig(string $tipeKegiatan): array
    {
        return match ($tipeKegiatan) {
            'prestasi' => [
                'search_column'      => 'lomba',
                'sortable_columns'   => [
                    'created_at', 'lomba', 'cabang', 'penyelenggara', 'level',
                    'kategori', 'peringkat', 'kelompok_prestasi', 'bentuk',
                    'tgl_sertifikat', 'status_internal', 'approved_at',
                ],
                'filterable_columns' => ['kategori', 'level'],
            ],
            'rekognisi' => [
                'search_column'      => 'nama',
                'sortable_columns'   => [
                    'created_at', 'nama', 'penyelenggara', 'level', 'jenis',
                    'tgl_sertifikat', 'status_internal', 'approved_at',
                ],
                'filterable_columns' => ['level'],
                'jenis_group_map'    => RekognisiService::JENIS_GROUP_MAP,
            ],
            'sertifikasi' => [
                'search_column'      => 'nama',
                'sortable_columns'   => [
                    'created_at', 'nama', 'penyelenggara', 'level',
                    'tgl_sertifikat', 'status_internal', 'approved_at',
                ],
                'filterable_columns' => ['level'],
            ],
            default => [
                'search_column'      => null,
                'sortable_columns'   => ['created_at'],
                'filterable_columns' => [],
            ],
        };
    }

    /**
     * Ambil daftar pengajuan dengan filter, sort, search, dan paginasi lengkap.
     *
     * @param string $tipeKegiatan 'prestasi', 'rekognisi', atau 'sertifikasi'
     * @param array $filters Query parameters dari request
     * @return LengthAwarePaginator|null null jika tipe kegiatan tidak valid
     */
    public function getQueue(string $tipeKegiatan, array $filters): ?LengthAwarePaginator
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        // Load relasi lengkap untuk tabel admin
        $query = $modelClass::with(['mahasiswa', 'dosen', 'creator:id,name', 'approver:id,name']);

        // Terapkan semua filter, search, dan sort via trait HasFilterSort
        $config = $this->getFilterConfig($tipeKegiatan);
        $query = $this->applyFilters($query, $filters, $config);

        return $query->paginate($this->getPaginationLimit($filters['limit'] ?? null));
    }

    /**
     * Ambil seluruh data pengajuan untuk export Excel (tanpa paginasi).
     * Filter yang sama dengan getQueue() diterapkan.
     *
     * @param string $tipeKegiatan 'prestasi', 'rekognisi', atau 'sertifikasi'
     * @param array $filters Query parameters dari request
     * @return \Illuminate\Support\Collection|null null jika tipe kegiatan tidak valid
     */
    public function getExportData(string $tipeKegiatan, array $filters): ?\Illuminate\Support\Collection
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        $query = $modelClass::with(['mahasiswa', 'dosen', 'creator:id,name', 'approver:id,name']);

        $config = $this->getFilterConfig($tipeKegiatan);
        $query = $this->applyFilters($query, $filters, $config);

        return $query->get();
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

        return $modelClass::with(['mahasiswa', 'dosen', 'creator:id,name', 'approver:id,name'])->find($id);
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
