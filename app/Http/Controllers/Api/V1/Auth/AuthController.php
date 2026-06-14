<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Controller Autentikasi (Kontrak_API_Frontend.md §A).
 * Thin Controller — logika minimal, validasi via FormRequest.
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * [POST] Login — Mendapatkan token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Email atau password salah.', 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        
        $user->update(['last_login_at' => now()]);

        // Ambil role dari Spatie Permission
        $role = $user->getRoleNames()->first(); // 'mahasiswa', 'admin', 'superadmin'

        // Ambil identitas berdasarkan role
        $identitas = null;
        if ($role === 'mahasiswa' && $user->mahasiswa) {
            $identitas = $user->mahasiswa->nim;
        }

        return $this->successResponse([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'identitas' => $identitas,
            ],
        ], 'Login berhasil.');
    }

    /**
     * [GET] Me — Profil user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first();

        $identitas = null;
        if ($role === 'mahasiswa' && $user->mahasiswa) {
            $identitas = $user->mahasiswa->nim;
        }

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'identitas' => $identitas,
        ], 'Profil berhasil diambil.');
    }

    /**
     * [POST] Logout — Invalidasi token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout berhasil.');
    }
}
