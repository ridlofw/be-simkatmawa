<?php

namespace App\Services\Superadmin;

use App\Traits\ResolvesModelType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Service Layer — Recycle Bin (Trash) Superadmin.
 * Mengelola logika query, detail, dan restore data yang di-soft-delete.
 */
class TrashService
{
    use ResolvesModelType;

    /**
     * Ambil daftar data di recycle bin.
     *
     * @return array|null null jika tipe kegiatan tidak valid
     */
    public function getTrashedItems(string $tipeKegiatan, int $limit, ?string $search, ?string $status): ?array
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan, true);

        if (!$modelClass) {
            return null;
        }

        // Hanya tarik yang terhapus (Soft Deleted)
        $query = $modelClass::onlyTrashed();

        // Relasi yang akan diload (beda untuk user dan kegiatan)
        if ($tipeKegiatan === 'user') {
            $query->with(['roles', 'deleter']);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
        } else {
            // Untuk prestasi, sertifikasi, rekognisi
            $query->with(['mahasiswa', 'deleter']);

            if ($search) {
                // Misal mencari judul kegiatan
                $kolomJudul = match ($tipeKegiatan) {
                    'prestasi' => 'lomba',
                    'sertifikasi' => 'nama',
                    'rekognisi' => 'nama',
                };

                $query->where($kolomJudul, 'like', "%{$search}%");
            }

            if ($status) {
                $query->where('status_internal', $status);
            }
        }

        $paginated = $query->latest('deleted_at')->paginate($limit);

        // Menyamakan format data untuk Frontend
        $data = collect($paginated->items())->map(function ($item) use ($tipeKegiatan) {
            return $this->formatTrashItem($item, $tipeKegiatan);
        });

        // Hitung total di tempat sampah untuk modul ini
        $totalTrash = $modelClass::onlyTrashed()->count();

        return [
            'data' => $data,
            'paginated' => $paginated,
            'totalTrash' => $totalTrash,
        ];
    }

    /**
     * Ambil detail data di recycle bin.
     *
     * @return Model|null null jika data tidak ditemukan
     */
    public function getTrashedDetail(string $tipeKegiatan, string $id): ?Model
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan, true);

        if (!$modelClass) {
            return null;
        }

        $query = $modelClass::onlyTrashed();

        if ($tipeKegiatan === 'user') {
            $query->with(['roles', 'deleter']);
        } else {
            $query->with(['mahasiswa', 'dosen', 'deleter']);
        }

        return $query->find($id);
    }

    /**
     * Memulihkan data (Restore).
     *
     * @return bool|null null jika tipe tidak valid, false jika data tidak ditemukan, true jika berhasil
     */
    public function restoreItem(string $tipeKegiatan, string $id): ?bool
    {
        $modelClass = $this->resolveModelClass($tipeKegiatan, true);

        if (!$modelClass) {
            return null;
        }

        $data = $modelClass::onlyTrashed()->find($id);

        if (!$data) {
            return false;
        }

        // Jalankan fungsi restore
        $data->restore();

        return true;
    }

    /**
     * Cek apakah tipe kegiatan valid (termasuk 'user').
     */
    public function isValidType(string $tipeKegiatan): bool
    {
        return $this->resolveModelClass($tipeKegiatan, true) !== null;
    }

    /**
     * Format satu item trash untuk response.
     */
    private function formatTrashItem(Model $item, string $tipeKegiatan): array
    {
        if ($tipeKegiatan === 'user') {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'email' => $item->email,
                'role' => $item->getRoleNames()->first() ?? '-',
                'deleted_at' => $item->deleted_at,
                'dihapus_oleh' => $item->deleter ? $item->deleter->name . ' (' . ($item->deleter->getRoleNames()->first() ?? '-') . ')' : '-',
            ];
        }

        $kolomJudul = match ($tipeKegiatan) {
            'prestasi' => 'lomba',
            'sertifikasi' => 'nama',
            'rekognisi' => 'nama',
        };

        // Pemilik bisa jadi lebih dari 1 mahasiswa (kelompok), ambil yang pertama atau list
        $pemilik = $item->mahasiswa->first();

        return [
            'id' => $item->id,
            'nama_kegiatan' => $item->{$kolomJudul},
            'pemilik' => $pemilik ? $pemilik->nama . ' (' . $pemilik->nim . ')' : '-',
            'status_terakhir' => $item->status_internal,
            'deleted_at' => $item->deleted_at,
            'dihapus_oleh' => $item->deleter ? $item->deleter->name . ' (' . ($item->deleter->getRoleNames()->first() ?? '-') . ')' : '-',
        ];
    }
}
