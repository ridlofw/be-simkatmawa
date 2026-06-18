<?php

namespace App\Services;

use App\Enums\Bentuk;
use App\Enums\JenisRekognisi;
use App\Enums\KategoriPrestasi;
use App\Enums\KelompokPrestasi;
use App\Enums\Level;
use App\Enums\Peringkat;
use App\Enums\StatusInternal;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Service Layer — Referensi (Kontrak_API_Frontend.md §B).
 * Menyediakan kamus data dropdown + lookup search untuk Frontend.
 */
class ReferensiService
{
    /**
     * Ambil semua data referensi enum untuk dropdown.
     */
    public function getEnums(): array
    {
        return [
            'level' => array_column(Level::cases(), 'value'),
            'kategori_prestasi' => array_column(KategoriPrestasi::cases(), 'value'),
            'peringkat' => array_column(Peringkat::cases(), 'value'),
            'kelompok_prestasi' => array_column(KelompokPrestasi::cases(), 'value'),
            'bentuk' => array_column(Bentuk::cases(), 'value'),
            'jenis_rekognisi' => array_column(JenisRekognisi::cases(), 'value'),
            'status_internal' => array_column(StatusInternal::cases(), 'value'),
            'roles' => Role::pluck('name')->toArray(),
        ];
    }

    /**
     * Lookup Mahasiswa — Cari berdasarkan Keyword Nama.
     * Digunakan Frontend untuk fitur dropdown search (?q=...).
     */
    public function searchMahasiswa(string $search): Collection
    {
        // Menambahkan wildcard asteriks untuk pencarian parsial di boolean mode
        $searchQuery = '*' . str_replace(' ', '* *', $search) . '*';

        return Mahasiswa::whereFullText('nama', $searchQuery, ['mode' => 'boolean'])
            ->limit(20)
            ->get(['nim', 'nama'])
            ->map(function ($item) {
                return [
                    'id' => $item->nim,
                    'nim' => $item->nim,
                    'nama' => $item->nama,
                    'label' => $item->nama . ' - ' . $item->nim,
                ];
            });
    }

    /**
     * Lookup Dosen — Cari berdasarkan Keyword Nama.
     * Digunakan Frontend untuk fitur dropdown search (?q=...).
     */
    public function searchDosen(string $search): Collection
    {
        // Menambahkan wildcard asteriks untuk pencarian parsial di boolean mode
        $searchQuery = '*' . str_replace(' ', '* *', $search) . '*';

        return Dosen::whereFullText('nama', $searchQuery, ['mode' => 'boolean'])
            ->limit(20)
            ->get(['nuptk', 'nama'])
            ->map(function ($item) {
                return [
                    'id' => $item->nuptk,
                    'nama' => $item->nama,
                    'nuptk' => $item->nuptk,
                    'label' => $item->nama . ' - ' . $item->nuptk,
                ];
            });
    }
}
