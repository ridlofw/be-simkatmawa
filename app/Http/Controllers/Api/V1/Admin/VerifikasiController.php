<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Import semua model yang dibutuhkan
use App\Models\PrestasiMandiri;
use App\Models\Sertifikasi;
use App\Models\Rekognisi;
use App\Jobs\SyncToKemdikbudJob;

/**
 * Controller Verifikasi Admin (Kontrak_API_Frontend.md §D).
 * Menangani approval/rejection pengajuan mahasiswa.
 */
class VerifikasiController extends Controller
{
    use ApiResponse;

    /**
     * Helper untuk menentukan Model berdasarkan parameter URL
     */
    private function getModelClass(string $tipeKegiatan)
    {
        return match (strtolower($tipeKegiatan)) {
            'prestasi' => PrestasiMandiri::class,
            'sertifikasi' => Sertifikasi::class,
            'rekognisi' => Rekognisi::class,
            default => null,
        };
    }

    /**
     * [GET] Daftar antrean pengajuan (filterable by status).
     */
    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            // Menggunakan helper errorResponse bawaan trait ApiResponse Anda
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        // Ambil parameter status dan limit dari URL, dengan nilai default
        $status = $request->query('status', 'PENDING');
        $limit = $request->query('limit', 10);

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

        // Eksekusi query dengan paginasi
        $paginated = $query->latest()->paginate($limit);

        // Kembalikan response JSON custom (karena paginate() bawaan strukturnya berbeda)
        return response()->json([
            'success' => true,
            'message' => "Data antrean $tipeKegiatan berhasil ditarik.",
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }

    /**
     * [GET] Detail pengajuan untuk review.
     */
    public function show(string $tipeKegiatan, int $id): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $data = $modelClass::with(['mahasiswa', 'dosen'])->find($id);

        if (!$data) {
            return $this->errorResponse("Data pengajuan tidak ditemukan.", 404);
        }

        return $this->successResponse($data, 'Detail pengajuan berhasil diambil.');
    }

    /**
     * [POST] Verifikasi pengajuan — Approve atau Reject.
     * Jika APPROVE → dispatch SyncToKemdikbudJob (background).
     */
    public function verifikasi(Request $request, string $tipeKegiatan, int $id): JsonResponse
    {
        $modelClass = $this->getModelClass($tipeKegiatan);

        if (!$modelClass) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $pengajuan = $modelClass::find($id);

        if (!$pengajuan) {
            return $this->errorResponse("Data pengajuan tidak ditemukan.", 404);
        }

        // Validasi input dari frontend
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:APPROVE,REJECT',
            'alasan_penolakan' => 'required_if:status,REJECT|nullable|string'
        ], [
            'alasan_penolakan.required_if' => 'Alasan penolakan wajib diisi jika menolak pengajuan.'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors()->toArray());
        }

        $status = $request->input('status');

        $adminId = $request->user()->id;
        $now = now();

        if ($status === 'APPROVE') {
            $pengajuan->update([
                'status_internal' => 'APPROVED_UNSYNCED',
                'alasan_penolakan' => null, // Reset alasan penolakan jika sebelumnya ditolak lalu disetujui ulang
                'approved_by' => $adminId,
                'approved_at' => $now,
            ]);

            // TODO: Dispatch job untuk sinkronisasi ke Kemdikbud
            // SyncToKemdikbudJob::dispatch($pengajuan);

        } elseif ($status === 'REJECT') {
            $pengajuan->update([
                'status_internal' => 'REJECTED',
                'alasan_penolakan' => $request->input('alasan_penolakan'),
                'approved_by' => $adminId,
                'approved_at' => $now,
            ]);
        }

        return $this->successResponse(null, "Verifikasi berhasil diproses. Status menjadi $status.");
    }
}
