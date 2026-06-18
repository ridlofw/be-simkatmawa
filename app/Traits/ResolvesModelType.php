<?php

namespace App\Traits;

use App\Models\PrestasiMandiri;
use App\Models\Rekognisi;
use App\Models\Sertifikasi;
use App\Models\User;

/**
 * Trait ResolvesModelType — Shared helper untuk resolusi Model class
 * berdasarkan parameter tipe kegiatan dari URL.
 *
 * Menggantikan duplikasi getModelClass() yang sebelumnya ada
 * di VerifikasiController, HistoryController, dan TrashController.
 */
trait ResolvesModelType
{
    /**
     * Resolve Model class berdasarkan tipe kegiatan.
     *
     * @param string $tipeKegiatan 'prestasi', 'sertifikasi', 'rekognisi', atau 'user'
     * @param bool $includeUser Apakah tipe 'user' diizinkan (khusus Superadmin Trash)
     * @return string|null Fully qualified class name atau null jika tidak valid
     */
    protected function resolveModelClass(string $tipeKegiatan, bool $includeUser = false): ?string
    {
        $map = [
            'prestasi' => PrestasiMandiri::class,
            'sertifikasi' => Sertifikasi::class,
            'rekognisi' => Rekognisi::class,
        ];

        if ($includeUser) {
            $map['user'] = User::class;
        }

        return $map[strtolower($tipeKegiatan)] ?? null;
    }
}
