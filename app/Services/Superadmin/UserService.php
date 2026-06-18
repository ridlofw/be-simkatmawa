<?php

namespace App\Services\Superadmin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

/**
 * Service Layer — User Management Superadmin.
 * Mengelola CRUD pengguna, statistik, dan deaktivasi akun.
 */
class UserService
{
    /**
     * Ambil daftar pengguna dengan fitur pencarian dan filter.
     */
    public function listUsers(int $limit, ?string $search, ?string $role, ?string $status): LengthAwarePaginator
    {
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

        return $query->latest()->paginate($limit);
    }

    /**
     * Format data paginated untuk response Frontend.
     */
    public function formatUserData(LengthAwarePaginator $paginated): array
    {
        return collect($paginated->items())->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? '-',
                'status' => $user->trashed() ? 'inactive' : 'active',
                'last_login_at' => $user->last_login_at ? $user->last_login_at->toISOString() : null,
                'created_at' => $user->created_at,
            ];
        })->toArray();
    }

    /**
     * Hitung statistik pengguna per role.
     */
    public function getUserStats(): array
    {
        return [
            'totalAdmin' => User::role('admin')->count(),
            'totalSuperadmin' => User::role('superadmin')->count(),
            'totalMahasiswa' => User::role('mahasiswa')->count(),
        ];
    }

    /**
     * Buat admin/superadmin baru.
     */
    public function createUser(array $validated): User
    {
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return $user;
    }

    /**
     * Update data admin/superadmin.
     *
     * @return User|null null jika user tidak ditemukan
     */
    public function updateUser(string $id, array $validated): ?User
    {
        // Pakai withTrashed kalau mau mengizinkan update pada akun inactive
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return null;
        }

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

        return $user;
    }

    /**
     * Menonaktifkan (soft delete) pengguna.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function deactivateUser(string $id, User $currentUser): array
    {
        $user = User::find($id);

        if (!$user) {
            return ['success' => false, 'message' => 'Pengguna tidak ditemukan atau sudah tidak aktif.', 'code' => 404];
        }

        // Hindari menghapus diri sendiri
        if ($user->id === $currentUser->id) {
            return ['success' => false, 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.', 'code' => 403];
        }

        $user->delete();

        return ['success' => true, 'message' => 'Pengguna berhasil dinonaktifkan.', 'code' => 200];
    }
}
