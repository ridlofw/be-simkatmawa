<?php

namespace App\Traits;

use App\Services\Rekognisi\RekognisiService;

/**
 * Trait HasFilterSort — Query builder dinamis yang aman dari SQL Injection.
 *
 * Menyediakan filter, search, sort, dan paginasi reusable
 * untuk semua service layer yang membutuhkan listing data dengan
 * parameter dinamis dari frontend.
 *
 * Keamanan:
 * - Sort column di-whitelist (hanya kolom yang terdaftar yang bisa digunakan)
 * - Filter column di-whitelist
 * - Semua input menggunakan parameter binding (bukan string concatenation)
 */
trait HasFilterSort
{
    /**
     * Terapkan semua filter, search, dan sort ke query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters Query parameters dari request
     * @param array $config Konfigurasi per-model:
     *   - search_column: string — Kolom utama untuk pencarian (misal 'lomba', 'nama')
     *   - sortable_columns: array — Daftar kolom yang boleh di-sort
     *   - filterable_columns: array — Daftar kolom yang bisa difilter langsung (misal ['kategori', 'level'])
     *   - jenis_group_map: array|null — Mapping jenis_group untuk Rekognisi (opsional)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyFilters($query, array $filters, array $config)
    {
        // 1. Filter Status
        $query = $this->applyStatusFilter($query, $filters);

        // 2. Filter Tahun
        $query = $this->applyTahunFilter($query, $filters);

        // 3. Filter Kolom Spesifik Model (kategori, level, dll)
        $query = $this->applyColumnFilters($query, $filters, $config['filterable_columns'] ?? []);

        // 4. Filter Jenis Group (khusus Rekognisi)
        $query = $this->applyJenisGroupFilter($query, $filters, $config['jenis_group_map'] ?? null);

        // 5. Search (nama kegiatan + mahasiswa nama/NIM)
        $query = $this->applySearch($query, $filters, $config['search_column'] ?? null);

        // 6. Sort (whitelisted columns only)
        $query = $this->applySort($query, $filters, $config['sortable_columns'] ?? []);

        return $query;
    }

    /**
     * Filter berdasarkan status_internal.
     * Mendukung: single value, multi value (koma), atau 'all'.
     */
    protected function applyStatusFilter($query, array $filters)
    {
        $status = $filters['status'] ?? 'all';

        if ($status !== 'all') {
            if (str_contains($status, ',')) {
                $query->whereIn('status_internal', explode(',', $status));
            } else {
                $query->where('status_internal', $status);
            }
        }

        return $query;
    }

    /**
     * Filter berdasarkan tahun (YEAR dari created_at).
     * Menggunakan whereYear() Laravel yang aman dari SQL Injection.
     */
    protected function applyTahunFilter($query, array $filters)
    {
        if (!empty($filters['tahun'])) {
            $tahun = (int) $filters['tahun'];
            if ($tahun > 0) {
                $query->whereYear('created_at', $tahun);
            }
        }

        return $query;
    }

    /**
     * Filter berdasarkan kolom yang di-whitelist (kategori, level, dll).
     * Hanya kolom yang ada di $allowedColumns yang akan diterapkan.
     */
    protected function applyColumnFilters($query, array $filters, array $allowedColumns)
    {
        foreach ($allowedColumns as $column) {
            if (!empty($filters[$column])) {
                $val = $filters[$column];

                // Normalisasi mapping level jika FE mengirim label seperti NASIONAL, WILAYAH, LOKAL, INTERNASIONAL
                if ($column === 'level') {
                    $levelMap = [
                        'LOKAL'         => 'KAB',
                        'KABUPATEN'     => 'KAB',
                        'KAB'           => 'KAB',
                        'WILAYAH'       => 'PROV',
                        'PROVINSI'      => 'PROV',
                        'PROV'          => 'PROV',
                        'NASIONAL'      => 'NAS',
                        'NAS'           => 'NAS',
                        'INTERNASIONAL' => 'INT',
                        'INT'           => 'INT',
                    ];
                    $upperVal = strtoupper($val);
                    $val = $levelMap[$upperVal] ?? $val;
                }

                $query->where($column, $val);
            }
        }

        return $query;
    }

    /**
     * Filter berdasarkan jenis_group (khusus Rekognisi).
     * Menerjemahkan slug sidebar FE (juri, keynote, dll) ke whereIn jenis enum.
     */
    protected function applyJenisGroupFilter($query, array $filters, ?array $jenisGroupMap)
    {
        if ($jenisGroupMap && !empty($filters['jenis_group'])) {
            $jenisValues = $jenisGroupMap[$filters['jenis_group']] ?? null;
            if ($jenisValues) {
                $query->whereIn('jenis', $jenisValues);
            }
        }

        return $query;
    }

    /**
     * Search berdasarkan kolom nama kegiatan + relasi mahasiswa (nama/NIM).
     * Menggunakan LIKE dengan parameter binding (aman SQL Injection).
     */
    protected function applySearch($query, array $filters, ?string $searchColumn)
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $searchColumn) {
                // Search di kolom nama kegiatan (lomba/nama)
                if ($searchColumn) {
                    $q->where($searchColumn, 'like', "%{$search}%");
                }

                // Search di relasi mahasiswa (nama + NIM)
                // Kualifikasi nama tabel mahasiswa.nama & mahasiswa.nim untuk mencegah SQL ambiguity error
                $q->orWhereHas('mahasiswa', function ($qMahasiswa) use ($search) {
                    $qMahasiswa->where(function ($qm) use ($search) {
                        $qm->where('mahasiswa.nama', 'like', "%{$search}%")
                           ->orWhere('mahasiswa.nim', 'like', "%{$search}%");
                    });
                });
            });
        }

        return $query;
    }

    /**
     * Sort berdasarkan kolom yang di-whitelist.
     * Jika sort_by tidak ada di whitelist → fallback ke created_at desc.
     * Mencegah SQL Injection karena hanya kolom terdaftar yang digunakan.
     */
    protected function applySort($query, array $filters, array $sortableColumns)
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc');

        // Whitelist validation — fallback jika kolom tidak valid
        if (!in_array($sortBy, $sortableColumns)) {
            $sortBy = 'created_at';
        }

        // Direction validation — hanya asc/desc yang valid
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        return $query->orderBy($sortBy, $sortDir);
    }
}
