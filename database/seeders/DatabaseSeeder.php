<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Urutan penting:
     * 1. Roles/Permissions (harus ada sebelum assignRole)
     * 2. Users (assignRole saat create)
     * 3. Mahasiswa (butuh FK user_id)
     * 4. Dosen (independen)
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class, // 1. Buat roles & permissions (Spatie)
            UserSeeder::class,           // 2. Buat akun login + assignRole
            MahasiswaSeeder::class,      // 3. Link NIM ke akun mahasiswa
            DosenSeeder::class,          // 4. Data master dosen (NIDN)
        ]);
    }
}
