<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Verifikasi Admin (Kontrak_API_Frontend.md §D).
 * Menangani approval/rejection pengajuan mahasiswa.
 */
class VerifikasiController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Daftar antrean pengajuan (filterable by status).
     */
    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        // TODO: Implementasi — ambil data sesuai $tipeKegiatan (prestasi/sertifikasi/rekognisi)
        return $this->successResponse([], 'Data antrean berhasil ditarik.');
    }

    /**
     * [GET] Detail pengajuan untuk review.
     */
    public function show(string $tipeKegiatan, int $id): JsonResponse
    {
        // TODO: Implementasi
        return $this->successResponse(null, 'Detail pengajuan berhasil diambil.');
    }

    /**
     * [POST] Verifikasi pengajuan — Approve atau Reject.
     * Jika APPROVE → dispatch SyncToKemdikbudJob (background).
     */
    public function verifikasi(Request $request, string $tipeKegiatan, int $id): JsonResponse
    {
        // TODO: Implementasi via VerifikasiService
        // - Validasi keputusan (APPROVE/REJECT)
        // - Jika REJECT: wajib alasan_penolakan
        // - Jika APPROVE: update status → APPROVED_UNSYNCED, dispatch job
        return $this->successResponse(null, 'Verifikasi berhasil diproses.');
    }
}
