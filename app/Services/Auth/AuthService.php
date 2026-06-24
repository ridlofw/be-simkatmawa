<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Service Layer — Autentikasi.
 * Mengelola logika login, profil pengguna, dan logout.
 */
class AuthService
{
    /**
     * Proses login: verifikasi kredensial, buat token, update last_login.
     *
     * @return array|null null jika kredensial salah
     */
    public function attemptLogin(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Update last_login tanpa mencatat ke activity log (bukan human action)
        activity()->withoutLogs(fn () => $user->update(['last_login_at' => now()]));

        // Ambil role dari Spatie Permission
        $role = $user->getRoleNames()->first(); // 'mahasiswa', 'admin', 'superadmin'

        // Ambil identitas berdasarkan role
        $identitas = null;
        if ($role === 'mahasiswa' && $user->mahasiswa) {
            $identitas = $user->mahasiswa->nim;
        }

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'identitas' => $identitas,
            ],
        ];
    }

    /**
     * Ambil profil user yang sedang login.
     */
    public function getUserProfile(User $user): array
    {
        $role = $user->getRoleNames()->first();

        $identitas = null;
        if ($role === 'mahasiswa' && $user->mahasiswa) {
            $identitas = $user->mahasiswa->nim;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'identitas' => $identitas,
        ];
    }

    /**
     * Logout — Invalidasi token saat ini.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
