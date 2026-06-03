<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $role = $request->query('role');

        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role && $role !== 'all') {
            $query->role($role);
        }

        $paginated = $query->latest()->paginate($limit);

        // Hitung statistik untuk Frontend
        $totalAdmin = User::role(['admin', 'superadmin'])->count();
        $totalMahasiswa = User::role('mahasiswa')->count();

        return response()->json([
            'success' => true,
            'message' => 'Data pengguna berhasil ditarik.',
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'stats'   => [
                'totalAdmin'     => $totalAdmin,
                'totalMahasiswa' => $totalMahasiswa,
            ]
        ]);
    }
}
