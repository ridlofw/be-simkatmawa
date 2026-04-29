<?php

namespace Database\Seeders;

use App\Models\Dosen;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk data master Dosen (NIDN/NUPTK).
 * Data dosen pembimbing yang digunakan saat pengajuan prestasi/sertifikasi/rekognisi.
 */
class DosenSeeder extends Seeder
{
    public function run(): void
    {
        Dosen::create([
            'nuptk' => '0622057501',
            'nama' => 'ETIKA KARTIKADARMA, M.Kom',
        ]);

        Dosen::create([
            'nuptk' => '0610118702',
            'nama' => 'WIKAN ISTHIKA, SE, M.Ec., Ak.',
        ]);
    }
}
