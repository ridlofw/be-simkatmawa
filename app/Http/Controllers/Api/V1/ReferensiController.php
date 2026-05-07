<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Bentuk;
use App\Enums\JenisRekognisi;
use App\Enums\KategoriPrestasi;
use App\Enums\KelompokPrestasi;
use App\Enums\Level;
use App\Enums\Peringkat;
use App\Enums\StatusInternal;
use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Controller Referensi (Kontrak_API_Frontend.md §B).
 * Menyediakan kamus data dropdown + lookup search untuk Frontend.
 */
class ReferensiController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Referensi Enums — Data dropdown untuk form Frontend.
     */
    public function enums(): JsonResponse
    {
        return $this->successResponse([
            'level' => array_column(Level::cases(), 'value'),
            'kategori_prestasi' => array_column(KategoriPrestasi::cases(), 'value'),
            'peringkat' => array_column(Peringkat::cases(), 'value'),
            'kelompok_prestasi' => array_column(KelompokPrestasi::cases(), 'value'),
            'bentuk' => array_column(Bentuk::cases(), 'value'),
            'jenis_rekognisi' => array_column(JenisRekognisi::cases(), 'value'),
            'status_internal' => array_column(StatusInternal::cases(), 'value'),
            'roles' => Role::pluck('name')->toArray(),
        ], 'Data referensi berhasil diambil.');
    }

    /**
     * [GET] Lookup Mahasiswa — Cari berdasarkan NIM.
     * Digunakan Frontend untuk validasi NIM secara real-time saat input peserta.
     *
     * Query: ?nim=A11.2024.16059
     */
    public function searchMahasiswa(Request $request): JsonResponse
    {
        $request->validate([
            'nim' => 'required|string|min:3',
        ]);

        $mahasiswa = Mahasiswa::where('nim', $request->nim)->first();

        if (!$mahasiswa) {
            return $this->errorResponse('Mahasiswa dengan NIM tersebut tidak ditemukan.', 404);
        }

        return $this->successResponse([
            'nim' => $mahasiswa->nim,
            'nama' => $mahasiswa->nama,
        ], 'Data mahasiswa ditemukan.');
    }

    /**
     * [GET] Lookup Dosen — Cari berdasarkan NUPTK/NIDN.
     * Digunakan Frontend untuk validasi NUPTK secara real-time saat input dosen.
     *
     * Query: ?nuptk=0622057501
     */
    public function searchDosen(Request $request): JsonResponse
    {
        $request->validate([
            'nuptk' => 'required|string|min:3',
        ]);

        $dosen = Dosen::where('nuptk', $request->nuptk)->first();

        if (!$dosen) {
            return $this->errorResponse('Dosen dengan NUPTK/NIDN tersebut tidak ditemukan.', 404);
        }

        return $this->successResponse([
            'nuptk' => $dosen->nuptk,
            'nama' => $dosen->nama,
        ], 'Data dosen ditemukan.');
    }
}
