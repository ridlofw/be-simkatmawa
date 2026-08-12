<?php

namespace Database\Seeders;

use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk data master Mahasiswa.
 * Menghubungkan NIM mahasiswa dengan akun User login mereka.
 */
class MahasiswaSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil user mahasiswa berdasarkan email untuk di-link ke NIM
        $ridlo = User::where('email', '111202416059@mhs.dinus.ac.id')->first();
        $adam = User::where('email', '111202415598@mhs.dinus.ac.id')->first();
        $syakira = User::where('email', '111202415594@mhs.dinus.ac.id')->first();

        Mahasiswa::create([
            'nim' => 'A11.2024.16059',
            'nama' => 'Ridlo Fanata Wicaksana',
            'user_id' => $ridlo?->id,
        ]);

        Mahasiswa::create([
            'nim' => 'A11.2024.15598',
            'nama' => 'Adam Raga',
            'user_id' => $adam?->id,
        ]);

        Mahasiswa::create([
            'nim' => 'A11.2024.15594',
            'nama' => 'Syakira Fara Salsabila',
            'user_id' => $syakira?->id,
        ]);
    }
}
