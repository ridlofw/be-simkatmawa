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
     * [GET] Lookup Mahasiswa — Cari berdasarkan Keyword Nama.
     * Digunakan Frontend untuk fitur dropdown search (?q=...).
     */
    public function searchMahasiswa(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string']);

        $search = $request->input('q');
        // Menambahkan wildcard asteriks untuk pencarian parsial di boolean mode
        $searchQuery = '*' . str_replace(' ', '* *', $search) . '*';

        $mahasiswa = Mahasiswa::whereFullText('nama', $searchQuery, ['mode' => 'boolean'])
            ->limit(20)
            ->get(['nim', 'nama'])
            ->map(function ($item) {
                return [
                    'id' => $item->nim,
                    'nim' => $item->nim,
                    'nama' => $item->nama,
                    'label' => $item->nama . ' - ' . $item->nim
                ];
            });

        return $this->successResponse($mahasiswa, 'Data mahasiswa berhasil diambil.');
    }

    /**
     * [GET] Lookup Dosen — Cari berdasarkan Keyword Nama.
     * Digunakan Frontend untuk fitur dropdown search (?q=...).
     */
    public function searchDosen(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string']);

        $search = $request->input('q');
        // Menambahkan wildcard asteriks untuk pencarian parsial di boolean mode
        $searchQuery = '*' . str_replace(' ', '* *', $search) . '*';

        $dosen = Dosen::whereFullText('nama', $searchQuery, ['mode' => 'boolean'])
            ->limit(20)
            ->get(['nuptk', 'nama'])
            ->map(function ($item) {
                return [
                    'id' => $item->nuptk,
                    'nama' => $item->nama,
                    'nuptk' => $item->nuptk,
                    'label' => $item->nama . ' - ' . $item->nuptk
                ];
            });

        return $this->successResponse($dosen, 'Data dosen berhasil diambil.');
    }
}
