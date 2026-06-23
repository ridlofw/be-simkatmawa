<?php

namespace App\Services\Rekognisi;

use App\Enums\StatusInternal;
use App\Models\Rekognisi;
use App\Models\User;
use App\Traits\HasPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service Layer — Rekognisi.
 * Pola identik dengan PrestasiService (State Machine PRD §2).
 */
class RekognisiService
{
    use HasPagination;

    public function getByMahasiswa(string $nim, array $filters = []): LengthAwarePaginator
    {
        $query = Rekognisi::whereHas('mahasiswa', function ($q) use ($nim) {
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

        return $query->orderByDesc('created_at')->paginate($this->getPaginationLimit($filters['limit'] ?? null));
    }

    public function findById(int $id): Rekognisi
    {
        return Rekognisi::with(['mahasiswa', 'dosen', 'creator:id,name', 'approver:id,name'])
            ->findOrFail($id);
    }

    public function create(array $validated, User $user): Rekognisi
    {
        return DB::transaction(function () use ($validated, $user) {
            $mahasiswaData = $validated['mahasiswa'];
            $dosenData = $validated['dosen'];
            unset($validated['mahasiswa'], $validated['dosen']);

            $rekognisi = Rekognisi::create(array_merge($validated, [
                'status_internal' => StatusInternal::PENDING,
                'created_by' => $user->id,
            ]));

            $nimList = collect($mahasiswaData)->pluck('nim')->toArray();
            $rekognisi->mahasiswa()->attach($nimList);

            $dosenPivot = [];
            foreach ($dosenData as $dosen) {
                $dosenPivot[$dosen['nuptk']] = ['url_surat_tugas' => $dosen['url_surat_tugas']];
            }
            $rekognisi->dosen()->attach($dosenPivot);

            $rekognisi->load(['mahasiswa', 'dosen']);
            return $rekognisi;
        });
    }

    public function update(int $id, array $validated, User $user): Rekognisi
    {
        return DB::transaction(function () use ($id, $validated, $user) {
            $rekognisi = Rekognisi::findOrFail($id);

            if ($rekognisi->created_by !== $user->id) {
                throw new AccessDeniedHttpException('Anda tidak memiliki izin untuk mengedit pengajuan ini.');
            }

            $editableStatuses = [StatusInternal::PENDING, StatusInternal::REJECTED];
            if (!in_array($rekognisi->status_internal, $editableStatuses)) {
                throw new AccessDeniedHttpException('Pengajuan tidak dapat diedit karena sudah diproses (status: ' . $rekognisi->status_internal->value . ').');
            }

            $mahasiswaData = $validated['mahasiswa'];
            $dosenData = $validated['dosen'];
            unset($validated['mahasiswa'], $validated['dosen']);

            if ($rekognisi->status_internal === StatusInternal::REJECTED) {
                $validated['status_internal'] = StatusInternal::PENDING;
                $validated['alasan_penolakan'] = null;
            }

            $rekognisi->update($validated);

            $nimList = collect($mahasiswaData)->pluck('nim')->toArray();
            $rekognisi->mahasiswa()->sync($nimList);

            $dosenPivot = [];
            foreach ($dosenData as $dosen) {
                $dosenPivot[$dosen['nuptk']] = ['url_surat_tugas' => $dosen['url_surat_tugas']];
            }
            $rekognisi->dosen()->sync($dosenPivot);

            $rekognisi->load(['mahasiswa', 'dosen']);
            return $rekognisi;
        });
    }

    public function delete(int $id, User $user): void
    {
        $rekognisi = Rekognisi::findOrFail($id);

        if ($rekognisi->created_by !== $user->id) {
            throw new AccessDeniedHttpException('Anda tidak memiliki izin untuk menghapus pengajuan ini.');
        }
        $deletableStatuses = [StatusInternal::PENDING, StatusInternal::REJECTED];
        if (!in_array($rekognisi->status_internal, $deletableStatuses)) {
            throw new AccessDeniedHttpException('Pengajuan tidak dapat dihapus karena sudah diproses (status: ' . $rekognisi->status_internal->value . ').');
        }

        $rekognisi->delete();
    }
}
