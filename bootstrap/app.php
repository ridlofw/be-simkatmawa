<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // 1. MATIKAN PENGECEKAN CSRF UNTUK SEMUA ROUTE API
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // 2. PASTIKAN STATEFUL MIDDLEWARE SANCTUM DIHAPUS / DI-COMMENT
        // $middleware->api(prepend: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            // Spatie Permission — middleware bawaan (menggantikan custom RoleMiddleware)
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * Global Exception Handler — Membungkus SEMUA error ke format kontrak API.
         * Sesuai Kontrak_API_Frontend.md §1:
         * { "success": false, "message": "...", "data": null, "errors": ... }
         */

        // 401 — Belum login / token expired
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
                    'data' => null,
                    'errors' => null,
                ], 401);
            }
        });

        // 403 — Tidak punya izin (role tidak sesuai)
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk mengakses resource ini.',
                    'data' => null,
                    'errors' => null,
                ], 403);
            }
        });

        // 403 — Spatie UnauthorizedException (role/permission mismatch)
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk mengakses resource ini.',
                    'data' => null,
                    'errors' => null,
                ], 403);
            }
        });

        // 404 — Route atau Model tidak ditemukan
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource yang diminta tidak ditemukan.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }
        });

        // 404 — Model::findOrFail() gagal
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $modelClass = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "Data {$modelClass} tidak ditemukan.",
                    'data' => null,
                    'errors' => null,
                ], 404);
            }
        });

        // 405 — Method HTTP tidak diizinkan
        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method HTTP tidak diizinkan untuk endpoint ini.',
                    'data' => null,
                    'errors' => null,
                ], 405);
            }
        });

        // 422 — Validasi gagal (FormRequest)
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal, periksa kembali input Anda.',
                    'data' => null,
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 500 — Error tak terduga (catch-all untuk API)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => null,
                    'errors' => config('app.debug') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : null,
                ], 500);
            }
        });
    })->create();
