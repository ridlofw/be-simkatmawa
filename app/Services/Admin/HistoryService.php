<?php

namespace App\Services\Admin;

use App\Traits\ResolvesModelType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Service Layer — History Pengajuan Admin.
 * Mengelola logika query riwayat pengajuan yang sudah diproses.
 */
class HistoryService
{
    use ResolvesModelType;

    /**
     * Cek apakah tipe kegiatan valid.
     */
    public function isValidType(string $tipeKegiatan): bool
    {
        return $this->resolveModelClass($tipeKegiatan) !== null;
    }

    /**
     * Ambil daftar history pengajuan.
     *
     * @return LengthAwarePaginator|null null jika tipe kegiatan tidak valid
     */
    public function getHistory(string $tipeKegiatan, int $limit, ?string $status, ?string $search): ?LengthAwarePaginator
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        $query = $modelClass::with('mahasiswa');

        // History: hanya data yang sudah diproses (bukan PENDING)
        // Kecuali admin request status secara eksplisit
        if ($status && $status !== 'all') {
            if (str_contains($status, ',')) {
                $query->whereIn('status_internal', explode(',', $status));
            } else {
                $query->where('status_internal', $status);
            }
        } else {
            // Default history: tampilkan yang sudah direview (APPROVED, REJECTED, dll)
            $query->where('status_internal', '!=', 'PENDING');
        }

        if ($search) {
             // Opsional: implementasi search, misal search berdasarkan judul
             $query->where(function($q) use ($search) {
                // Untuk PrestasiMandiri ada 'lomba', Sertifikasi ada 'nama_sertifikasi', Rekognisi ada 'nama_kegiatan'
                // Karena kita menggunakan Model Class secara dinamis, sebaiknya menggunakan whereHas mahasiswa atau generic
                $q->whereHas('mahasiswa', function($qMahasiswa) use ($search) {
                    $qMahasiswa->where('nama', 'like', "%{$search}%")
                               ->orWhere('nim', 'like', "%{$search}%");
                });
             });
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Ambil detail history pengajuan.
     *
     * @return Model|null null jika data tidak ditemukan
     */
    public function getHistoryDetail(string $tipeKegiatan, int $id): ?Model
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan);

        if (!$modelClass) {
            return null;
        }

        return $modelClass::with(['mahasiswa', 'dosen'])->find($id);
    }
}
