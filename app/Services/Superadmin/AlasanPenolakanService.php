<?php

namespace App\Services\Superadmin;

use App\Models\AlasanPenolakan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service Layer — Master Alasan Penolakan Superadmin.
 * Mengelola CRUD template alasan penolakan dan data referensi penolakan.
 */
class AlasanPenolakanService
{
    /**
     * Ambil daftar master alasan penolakan dengan paginasi & filter search.
     */
    public function listReasons(int $limit, ?string $search, ?bool $isActive): LengthAwarePaginator
    {
        $query = AlasanPenolakan::with(['creator:id,name', 'updater:id,name']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('alasan', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Ambil detail master alasan penolakan.
     */
    public function getDetail(int $id): ?AlasanPenolakan
    {
        return AlasanPenolakan::with(['creator:id,name', 'updater:id,name'])->find($id);
    }

    /**
     * Buat master alasan penolakan baru.
     */
    public function createReason(array $validated, string $userId): AlasanPenolakan
    {
        return AlasanPenolakan::create([
            'judul' => $validated['judul'],
            'alasan' => $validated['alasan'],
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Update master alasan penolakan.
     */
    public function updateReason(int $id, array $validated, string $userId): ?AlasanPenolakan
    {
        $reason = AlasanPenolakan::find($id);

        if (!$reason) {
            return null;
        }

        $dataToUpdate = [
            'updated_by' => $userId,
        ];

        if (isset($validated['judul'])) {
            $dataToUpdate['judul'] = $validated['judul'];
        }

        if (isset($validated['alasan'])) {
            $dataToUpdate['alasan'] = $validated['alasan'];
        }

        if (isset($validated['is_active'])) {
            $dataToUpdate['is_active'] = $validated['is_active'];
        }

        $reason->update($dataToUpdate);

        return $reason->fresh(['creator:id,name', 'updater:id,name']);
    }

    /**
     * Hapus (soft delete) master alasan penolakan.
     */
    public function deleteReason(int $id, string $userId): bool
    {
        $reason = AlasanPenolakan::find($id);

        if (!$reason) {
            return false;
        }

        $reason->update(['deleted_by' => $userId]);
        return (bool) $reason->delete();
    }

    /**
     * Ambil daftar alasan penolakan yang aktif saja (untuk referensi dropdown).
     */
    public function getActiveReasons(): Collection
    {
        return AlasanPenolakan::where('is_active', true)
            ->select(['id', 'judul', 'alasan'])
            ->orderBy('judul', 'asc')
            ->get();
    }
}
