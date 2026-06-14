<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * [GET] List semua pengguna dengan fitur pencarian dan filter
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $role = $request->query('role');
        $status = $request->query('status'); // 'active', 'inactive'

        // withTrashed agar user yang di-soft-delete (inactive) tetap muncul
        $query = User::with('roles')->withTrashed();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role && $role !== 'all') {
            $query->role($role);
        }

        if ($status === 'active') {
            $query->whereNull('deleted_at');
        } elseif ($status === 'inactive') {
            $query->whereNotNull('deleted_at');
        }

        $paginated = $query->latest()->paginate($limit);

        // Format data untuk mempermudah frontend membaca status dan role
        $data = collect($paginated->items())->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? '-',
                'status' => $user->trashed() ? 'inactive' : 'active',
                'last_login_at' => $user->last_login_at ? $user->last_login_at->toISOString() : null,
                'created_at' => $user->created_at,
            ];
        });

        // Hitung statistik untuk Dashboard/Frontend
        $totalAdmin = User::role('admin')->count();
        $totalSuperadmin = User::role('superadmin')->count();
        $totalMahasiswa = User::role('mahasiswa')->count();

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
            'stats'   => [
                'totalAdmin'     => $totalAdmin,
                'totalSuperadmin' => $totalSuperadmin,
                'totalMahasiswa' => $totalMahasiswa,
            ]
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

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

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
        // Pakai withTrashed kalau mau mengizinkan update pada akun inactive
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return $this->errorResponse('Pengguna tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', Password::min(8)],
            'role' => 'sometimes|required|in:admin,superadmin'
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return $this->successResponse(
            ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->getRoleNames()->first()],
            'Data pengguna berhasil diperbarui.'
        );
    }

    /**
     * [DELETE] Menonaktifkan (soft delete) pengguna
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse('Pengguna tidak ditemukan atau sudah tidak aktif.', 404);
        }

        // Hindari menghapus diri sendiri
        if ($user->id === auth()->id()) {
            return $this->errorResponse('Anda tidak dapat menonaktifkan akun Anda sendiri.', 403);
        }

        $user->delete();

        return $this->successResponse(null, 'Pengguna berhasil dinonaktifkan.');
    }
}
