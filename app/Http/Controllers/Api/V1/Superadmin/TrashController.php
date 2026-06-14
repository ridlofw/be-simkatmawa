<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PrestasiMandiri;
use App\Models\Rekognisi;
use App\Models\Sertifikasi;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    use ApiResponse;

    /**
     * Helper untuk menentukan Model berdasarkan tipe kegiatan
     */
    private function getModelClass(string $tipeKegiatan)
    {
        return match (strtolower($tipeKegiatan)) {
            'prestasi' => PrestasiMandiri::class,
            'sertifikasi' => Sertifikasi::class,
            'rekognisi' => Rekognisi::class,
            'user' => User::class,
            default => null,
        };
    }

    /**
     * [GET] List data di recycle bin
     */
    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $status = $request->query('status'); // untuk filter status_internal jika ada

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
            
            if ($tipeKegiatan === 'user') {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'role' => $item->getRoleNames()->first() ?? '-',
                    'deleted_at' => $item->deleted_at,
                    'dihapus_oleh' => $item->deleter ? $item->deleter->name . ' (' . ($item->deleter->getRoleNames()->first() ?? '-') . ')' : '-',
                ];
            } else {
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
        });

        // Hitung total di tempat sampah untuk modul ini
        $totalTrash = $modelClass::onlyTrashed()->count();

        return response()->json([
            'success' => true,
            'message' => "Data keranjang sampah $tipeKegiatan berhasil ditarik.",
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'stats'   => [
                "total_trash_{$tipeKegiatan}" => $totalTrash
            ]
        ]);
    }

    /**
     * [GET] Detail data di recycle bin
     */
    public function show(string $tipeKegiatan, string $id): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $query = $modelClass::onlyTrashed();

        if ($tipeKegiatan === 'user') {
            $query->with(['roles', 'deleter']);
        } else {
            $query->with(['mahasiswa', 'dosen', 'deleter']);
        }

        $data = $query->find($id);

        if (!$data) {
            return $this->errorResponse("Data tidak ditemukan di keranjang sampah.", 404);
        }

        return $this->successResponse($data, 'Detail data keranjang sampah berhasil diambil.');
    }

    /**
     * [PUT] Memulihkan data (Restore)
     */
    public function restore(string $tipeKegiatan, string $id): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $data = $modelClass::onlyTrashed()->find($id);

        if (!$data) {
            return $this->errorResponse("Data tidak ditemukan di keranjang sampah.", 404);
        }

        // Jalankan fungsi restore
        $data->restore();

        return $this->successResponse(null, "Data $tipeKegiatan berhasil dipulihkan.");
    }
}
