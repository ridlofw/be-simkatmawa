<?php

namespace App\Services\Prestasi;

use App\Enums\StatusInternal;
use App\Models\PrestasiMandiri;
use App\Services\NotificationService;
use App\Models\User;
use App\Traits\HasPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service Layer — Prestasi Mandiri.
 * Seluruh logika bisnis padat berada di sini (PRD §2 State Machine).
 *
 * State Machine:
 * PENDING → (Admin Approve) → APPROVED_UNSYNCED → (Job) → SYNC_SUCCESS / SYNC_FAILED
 * PENDING → (Admin Reject) → REJECTED → (Mahasiswa Edit) → PENDING (loop)
 * PENDING → (Mahasiswa Delete) → Soft Deleted
 */
class PrestasiService
{
    use HasPagination;

    // ========================================================================
    // READ OPERATIONS
    // ========================================================================

    /**
     * Ambil semua prestasi yang diikuti mahasiswa (berdasarkan NIM di pivot).
     * Scope: bukan hanya yang dia buat, tapi semua yang NIM-nya terdaftar sebagai peserta.
     *
     * @param string $nim NIM mahasiswa
     * @param array $filters Optional: ['status' => 'PENDING', 'search' => 'badminton']
     * @return LengthAwarePaginator
     */
    public function getByMahasiswa(string $nim, array $filters = []): LengthAwarePaginator
    {
        $query = PrestasiMandiri::whereHas('mahasiswa', function ($q) use ($nim) {
            $q->where('mahasiswa.nim', $nim);
        })->with(['mahasiswa', 'dosen', 'creator:id,name']);

        // Filter by status (PENDING, APPROVED_UNSYNCED, REJECTED, dll)
        if (!empty($filters['status'])) {
            $query->where('status_internal', $filters['status']);
        }

        // Filter by level (LOKAL, WILAYAH, NAS, INT)
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        // Search by nama lomba
        if (!empty($filters['search'])) {
            $query->where('lomba', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderByDesc('created_at')->paginate($this->getPaginationLimit($filters['limit'] ?? null));
    }

    /**
     * Ambil detail prestasi by ID, beserta relasi mahasiswa & dosen.
     *
     * @throws ModelNotFoundException
     */
    public function findById(int $id): PrestasiMandiri
    {
        return PrestasiMandiri::with(['mahasiswa', 'dosen', 'creator:id,name', 'approver:id,name'])
            ->findOrFail($id);
    }

    // ========================================================================
    // CREATE
    // ========================================================================

    /**
     * Buat pengajuan prestasi baru.
     * - Set status = PENDING, created_by = user login
     * - Attach mahasiswa[] dan dosen[] ke pivot tables
     * - Wrapped dalam DB Transaction untuk atomicity
     *
     * @param array $validated Data yang sudah divalidasi dari FormRequest
     * @param User $user User yang sedang login
     * @return PrestasiMandiri
     */
    public function create(array $validated, User $user): PrestasiMandiri
    {
        $result = DB::transaction(function () use ($validated, $user) {
            // 1. Pisahkan data pivot dari data utama
            $mahasiswaData = $validated['mahasiswa'];
            $dosenData = $validated['dosen'];
            unset($validated['mahasiswa'], $validated['dosen']);

            // 2. Buat record utama
            $prestasi = PrestasiMandiri::create(array_merge($validated, [
                'status_internal' => StatusInternal::PENDING,
                'created_by' => $user->id,
            ]));

            // 3. Attach mahasiswa ke pivot (nim saja, tanpa kolom tambahan)
            $nimList = collect($mahasiswaData)->pluck('nim')->toArray();
            $prestasi->mahasiswa()->attach($nimList);

            // 4. Attach dosen ke pivot (nuptk + url_surat_tugas sebagai pivot data)
            $dosenPivot = [];
            foreach ($dosenData as $dosen) {
                $dosenPivot[$dosen['nuptk']] = [
                    'url_surat_tugas' => $dosen['url_surat_tugas'],
                ];
            }
            $prestasi->dosen()->attach($dosenPivot);

            // 5. Load relasi untuk response
            $prestasi->load(['mahasiswa', 'dosen']);

            return $prestasi;
        });

        // 6. Kirim notifikasi "Pengajuan Terkirim" ke mahasiswa (di luar transaction)
        app(NotificationService::class)->submissionSent($result, $user);

        return $result;
    }

    // ========================================================================
    // UPDATE
    // ========================================================================

    /**
     * Update pengajuan prestasi.
     *
     * Aturan State Machine (PRD §2):
     * - Hanya bisa edit jika status PENDING atau REJECTED
     * - Hanya created_by yang bisa edit
     * - Jika dari REJECTED → status reset ke PENDING, alasan_penolakan di-clear
     *
     * @throws AccessDeniedHttpException Jika bukan pemilik atau status locked
     */
    public function update(int $id, array $validated, User $user): PrestasiMandiri
    {
        return DB::transaction(function () use ($id, $validated, $user) {
            $prestasi = PrestasiMandiri::findOrFail($id);

            // Guard 1: Ownership — hanya pembuat yang boleh edit
            if ($prestasi->created_by !== $user->id) {
                throw new AccessDeniedHttpException(
                    'Anda tidak memiliki izin untuk mengedit pengajuan ini.'
                );
            }

            // Guard 2: State Machine — hanya PENDING atau REJECTED yang bisa diedit
            $editableStatuses = [StatusInternal::PENDING, StatusInternal::REJECTED];
            if (!in_array($prestasi->status_internal, $editableStatuses)) {
                throw new AccessDeniedHttpException(
                    'Pengajuan tidak dapat diedit karena sudah diproses (status: ' . $prestasi->status_internal->value . ').'
                );
            }

            // 1. Pisahkan data pivot
            $mahasiswaData = $validated['mahasiswa'];
            $dosenData = $validated['dosen'];
            unset($validated['mahasiswa'], $validated['dosen']);

            // 2. Reset status jika dari REJECTED → PENDING (PRD §3.3 Rejection Flow)
            $wasRejected = $prestasi->status_internal === StatusInternal::REJECTED;
            if ($wasRejected) {
                $validated['status_internal'] = StatusInternal::PENDING;
                $validated['alasan_penolakan'] = null;
            }

            // 3. Update record utama
            $prestasi->update($validated);

            // 4. Sync pivot tables (replace all existing with new data)
            $nimList = collect($mahasiswaData)->pluck('nim')->toArray();
            $prestasi->mahasiswa()->sync($nimList);

            $dosenPivot = [];
            foreach ($dosenData as $dosen) {
                $dosenPivot[$dosen['nuptk']] = [
                    'url_surat_tugas' => $dosen['url_surat_tugas'],
                ];
            }
            $prestasi->dosen()->sync($dosenPivot);

            // 5. Refresh model + relasi
            $prestasi->load(['mahasiswa', 'dosen']);

            // 6. Notifikasi ke admin yang menolak: "Revisi Telah Diperbaiki"
            if ($wasRejected) {
                app(NotificationService::class)->revisionResubmitted($prestasi);
            }

            return $prestasi;
        });
    }

    // ========================================================================
    // DELETE (Soft Delete)
    // ========================================================================

    /**
     * Soft delete pengajuan prestasi.
     *
     * Aturan State Machine (PRD §2):
     * - Hanya bisa hapus jika status PENDING
     * - Hanya created_by yang bisa hapus
     * - Data masuk ke Recycle Bin (Superadmin bisa restore/force delete)
     *
     * @throws AccessDeniedHttpException
     */
    public function delete(int $id, User $user): void
    {
        $prestasi = PrestasiMandiri::findOrFail($id);

        // Guard 1: Ownership
        if ($prestasi->created_by !== $user->id) {
            throw new AccessDeniedHttpException(
                'Anda tidak memiliki izin untuk menghapus pengajuan ini.'
            );
        }

        // Guard 2: Hanya PENDING atau REJECTED yang bisa dihapus mahasiswa
        $deletableStatuses = [StatusInternal::PENDING, StatusInternal::REJECTED];
        if (!in_array($prestasi->status_internal, $deletableStatuses)) {
            throw new AccessDeniedHttpException(
                'Pengajuan tidak dapat dihapus karena sudah diproses (status: ' . $prestasi->status_internal->value . ').'
            );
        }

        $prestasi->delete(); // Soft Delete — data tetap ada di DB
    }
}
