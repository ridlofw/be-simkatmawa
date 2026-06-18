<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Autentikasi (Kontrak_API_Frontend.md §A).
 * Thin Controller — logika minimal, delegasi ke AuthService.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * [POST] Login — Mendapatkan token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->attemptLogin(
            $request->email,
            $request->password
        );

        if (!$result) {
            return $this->errorResponse('Email atau password salah.', 401);
        }

        return $this->successResponse($result, 'Login berhasil.');
    }

    /**
     * [GET] Me — Profil user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->authService->getUserProfile($request->user());

        return $this->successResponse($profile, 'Profil berhasil diambil.');
    }

    /**
     * [POST] Logout — Invalidasi token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logout berhasil.');
    }
}
