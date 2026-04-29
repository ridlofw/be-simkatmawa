<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder untuk akun pengguna Simkatmawa Udinus.
 * Role di-assign via Spatie Permission (bukan kolom enum).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ========== SUPERADMIN ==========
        $superadmin = User::create([
            'name' => 'Superadmin Simkatmawa',
            'email' => 'superadmin@dinus.ac.id',
            'password' => Hash::make('milikbima'),
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        // ========== ADMIN (Dosen Verifikator) ==========
        $admin = User::create([
            'name' => 'Etika Kartikadarma, M.Kom',
            'email' => 'etikakartikadarma@dsn.dinus.ac.id',
            'password' => Hash::make('admin'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // ========== MAHASISWA ==========
        $mhs1 = User::create([
            'name' => 'Ridlo Fanata Wicaksana',
            'email' => '111202416059@mhs.dinus.ac.id',
            'password' => Hash::make('mahasiswa1'),
            'email_verified_at' => now(),
        ]);
        $mhs1->assignRole('mahasiswa');

        $mhs2 = User::create([
            'name' => 'Adam Raga',
            'email' => '111202416098@mhs.dinus.ac.id',
            'password' => Hash::make('mahasiswa2'),
            'email_verified_at' => now(),
        ]);
        $mhs2->assignRole('mahasiswa');

        $mhs3 = User::create([
            'name' => 'Syakira Fara Salsabila',
            'email' => '111202416094@mhs.dinus.ac.id',
            'password' => Hash::make('mahasiswa3'),
            'email_verified_at' => now(),
        ]);
        $mhs3->assignRole('mahasiswa');
    }
}
