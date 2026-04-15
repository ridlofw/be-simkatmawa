<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware RBAC — Memblokir akses berdasarkan Role pengguna.
 *
 * Usage di route: ->middleware('role:admin') atau ->middleware('role:admin,superadmin')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
                'data' => null,
                'errors' => null,
            ], 401);
        }

        // Konversi string roles ke enum values untuk perbandingan
        $allowedRoles = array_map(fn(string $role) => trim($role), $roles);

        if (!in_array($user->role->value, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengakses resource ini.',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
