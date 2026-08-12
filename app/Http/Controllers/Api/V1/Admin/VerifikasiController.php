<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\VerifikasiService;
use App\Traits\ApiResponse;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Verifikasi Admin (Kontrak_API_Frontend.md §D).
 * Thin Controller — delegasi logika bisnis ke VerifikasiService.
 */
class VerifikasiController extends Controller
{
    use ApiResponse, HasPagination;

    public function __construct(
        private readonly VerifikasiService $verifikasiService
    ) {}

    /**
     * [GET] Daftar pengajuan (unified: filter, sort, search, paginasi).
     */
    public function index(Request $request, string $tipeKegiatan): JsonResponse
    {
        if (!$this->verifikasiService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        // Collect semua query parameter filter/sort/search
        $filters = $request->only([
            'status', 'kategori', 'jenis_group', 'level', 'tahun',
            'search', 'sort_by', 'sort_dir', 'limit', 'page'
        ]);

        $paginated = $this->verifikasiService->getQueue($tipeKegiatan, $filters);

        return response()->json([
            'success' => true,
            'message' => "Data daftar $tipeKegiatan berhasil ditarik.",
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
        if (!$this->verifikasiService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        $data = $this->verifikasiService->getDetail($tipeKegiatan, $id);

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
        if (!$this->verifikasiService->isValidType($tipeKegiatan)) {
            return $this->errorResponse("Tipe kegiatan '$tipeKegiatan' tidak valid.", 400);
        }

        // Validasi input dari frontend
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:APPROVE,REJECT',
            'alasan_penolakan_id' => 'nullable|integer|exists:alasan_penolakan,id',
            'alasan_penolakan' => 'nullable|string'
        ]);

        $validator->after(function ($v) use ($request) {
            if ($request->input('status') === 'REJECT') {
                if (!$request->input('alasan_penolakan_id') && empty(trim($request->input('alasan_penolakan', '')))) {
                    $v->errors()->add('alasan_penolakan', 'Alasan penolakan atau pilihan alasan cepat wajib diisi jika menolak pengajuan.');
                }
            }
        });

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors()->toArray());
        }

        $status = $request->input('status');

        $pengajuan = $this->verifikasiService->processVerification(
            $tipeKegiatan,
            $id,
            $status,
            $request->input('alasan_penolakan'),
            $request->user(),
            $request->input('alasan_penolakan_id')
        );

        if (!$pengajuan) {
            return $this->errorResponse("Data pengajuan tidak ditemukan.", 404);
        }

        return $this->successResponse(null, "Verifikasi berhasil diproses. Status menjadi $status.");
    }
}
