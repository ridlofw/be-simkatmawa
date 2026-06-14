<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\PrestasiMandiri;
use App\Models\Sertifikasi;
use App\Models\Rekognisi;

class HistoryController extends Controller
{
    use ApiResponse;

    private function getModelClass(string $tipeKegiatan)
    {
        return match (strtolower($tipeKegiatan)) {
            'prestasi' => PrestasiMandiri::class,
            'sertifikasi' => Sertifikasi::class,
            'rekognisi' => Rekognisi::class,
            default => null,
        };
    }

    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $limit = $request->query('limit', 10);
        $status = $request->query('status');
        $search = $request->query('search');

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

        $paginated = $query->latest()->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => "Data history $tipeKegiatan berhasil ditarik.",
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }

    public function show(string $tipeKegiatan, int $id): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $data = $modelClass::with(['mahasiswa', 'dosen'])->find($id);

        if (!$data) {
            return $this->errorResponse("Data history tidak ditemukan.", 404);
        }

        return $this->successResponse($data, 'Detail history berhasil diambil.');
    }
}
