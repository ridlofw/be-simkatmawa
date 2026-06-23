<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Superadmin\UserService;
use App\Traits\ApiResponse;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use ApiResponse, HasPagination;

    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * [GET] List semua pengguna dengan fitur pencarian dan filter
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $this->getPaginationLimit($request->query('limit'));
        $search = $request->query('search');
        $role = $request->query('role');
        $status = $request->query('status'); // 'active', 'inactive'

        $paginated = $this->userService->listUsers($limit, $search, $role, $status);

        // Format data untuk mempermudah frontend membaca status dan role
        $data = $this->userService->formatUserData($paginated);

        // Hitung statistik untuk Dashboard/Frontend
        $stats = $this->userService->getUserStats();

        return response()->json([
            'success' => true,
            'message' => 'Data pengguna berhasil ditarik.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'stats'   => $stats,
        ]);
    }

    /**
     * [POST] Buat admin/superadmin baru
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Password::min(8)],
            'role' => 'required|in:admin,superadmin' // Mahasiswa harusnya mendaftar otomatis via SSO
        ]);

        $user = $this->userService->createUser($validated);

        return $this->successResponse(
            ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $validated['role']],
            'Pengguna berhasil ditambahkan.',
            201
        );
    }

    /**
     * [PUT] Update data admin/superadmin
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Validasi dasar dulu sebelum cari user (agar consistent)
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => ['nullable', Password::min(8)],
            'role' => 'sometimes|required|in:admin,superadmin'
        ]);

        $user = $this->userService->updateUser($id, $validated);

        if (!$user) {
            return $this->errorResponse('Pengguna tidak ditemukan.', 404);
        }

        return $this->successResponse(
            ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->getRoleNames()->first()],
            'Data pengguna berhasil diperbarui.'
        );
    }

    /**
     * [DELETE] Menonaktifkan (soft delete) pengguna
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $result = $this->userService->deactivateUser($id, $request->user());

        if (!$result['success']) {
            return $this->errorResponse($result['message'], $result['code']);
        }

        return $this->successResponse(null, $result['message']);
    }
}
