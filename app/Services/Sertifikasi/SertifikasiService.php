<?php

namespace App\Services\Sertifikasi;

use App\Enums\StatusInternal;
use App\Models\Sertifikasi;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service Layer — Sertifikasi.
 * Pola identik dengan PrestasiService (State Machine PRD §2).
 */
class SertifikasiService
{
    public function getByMahasiswa(string $nim, array $filters = []): LengthAwarePaginator
    {
        $query = Sertifikasi::whereHas('mahasiswa', function ($q) use ($nim) {
            $q->where('mahasiswa.nim', $nim);
        })->with(['mahasiswa', 'dosen', 'creator:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status_internal', $filters['status']);
        }
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (!empty($filters['search'])) {
            $query->where('nama', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderByDesc('created_at')->paginate(15);
    }

    public function findById(int $id): Sertifikasi
    {
        return Sertifikasi::with(['mahasiswa', 'dosen', 'creator:id,name', 'approver:id,name'])
            ->findOrFail($id);
    }

    public function create(array $validated, User $user): Sertifikasi
    {
        return DB::transaction(function () use ($validated, $user) {
            $mahasiswaData = $validated['mahasiswa'];
            $dosenData = $validated['dosen'];
            unset($validated['mahasiswa'], $validated['dosen']);

            $sertifikasi = Sertifikasi::create(array_merge($validated, [
                'status_internal' => StatusInternal::PENDING,
                'created_by' => $user->id,
            ]));

            $nimList = collect($mahasiswaData)->pluck('nim')->toArray();
            $sertifikasi->mahasiswa()->attach($nimList);

            $dosenPivot = [];
            foreach ($dosenData as $dosen) {
                $dosenPivot[$dosen['nuptk']] = ['url_surat_tugas' => $dosen['url_surat_tugas']];
            }
            $sertifikasi->dosen()->attach($dosenPivot);

            $sertifikasi->load(['mahasiswa', 'dosen']);
            return $sertifikasi;
        });
    }

    public function update(int $id, array $validated, User $user): Sertifikasi
    {
        return DB::transaction(function () use ($id, $validated, $user) {
            $sertifikasi = Sertifikasi::findOrFail($id);

            if ($sertifikasi->created_by !== $user->id) {
                throw new AccessDeniedHttpException('Anda tidak memiliki izin untuk mengedit pengajuan ini.');
            }

            $editableStatuses = [StatusInternal::PENDING, StatusInternal::REJECTED];
            if (!in_array($sertifikasi->status_internal, $editableStatuses)) {
                throw new AccessDeniedHttpException('Pengajuan tidak dapat diedit karena sudah diproses (status: ' . $sertifikasi->status_internal->value . ').');
            }

            $mahasiswaData = $validated['mahasiswa'];
            $dosenData = $validated['dosen'];
            unset($validated['mahasiswa'], $validated['dosen']);

            if ($sertifikasi->status_internal === StatusInternal::REJECTED) {
                $validated['status_internal'] = StatusInternal::PENDING;
                $validated['alasan_penolakan'] = null;
            }

            $sertifikasi->update($validated);

            $nimList = collect($mahasiswaData)->pluck('nim')->toArray();
            $sertifikasi->mahasiswa()->sync($nimList);

            $dosenPivot = [];
            foreach ($dosenData as $dosen) {
                $dosenPivot[$dosen['nuptk']] = ['url_surat_tugas' => $dosen['url_surat_tugas']];
            }
            $sertifikasi->dosen()->sync($dosenPivot);

            $sertifikasi->load(['mahasiswa', 'dosen']);
            return $sertifikasi;
        });
    }

    public function delete(int $id, User $user): void
    {
        $sertifikasi = Sertifikasi::findOrFail($id);

        if ($sertifikasi->created_by !== $user->id) {
            throw new AccessDeniedHttpException('Anda tidak memiliki izin untuk menghapus pengajuan ini.');
        }
        if ($sertifikasi->status_internal !== StatusInternal::PENDING) {
            throw new AccessDeniedHttpException('Pengajuan tidak dapat dihapus karena sudah diproses (status: ' . $sertifikasi->status_internal->value . ').');
        }

        $sertifikasi->delete();
    }
}
