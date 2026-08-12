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
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // 0. TRUST PROXIES — Diperlukan untuk Cloudflare Tunnel / reverse proxy
        //    Agar Laravel membaca X-Forwarded-* headers dengan benar (HTTPS, IP asli)
        $middleware->trustProxies(at: '*');

        // 1. MATIKAN PENGECEKAN CSRF UNTUK SEMUA ROUTE API & BROADCASTING AUTH
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'broadcasting/auth',
        ]);

        // 2. SANCTUM STATEFUL API — Menangani CORS preflight (OPTIONS) dengan benar
        //    Tanpa ini, browser mengirim OPTIONS request yang tidak dijawab → timeout.
        $middleware->statefulApi();

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
         *
         * BEST PRACTICE: Karena proyek ini 100% API-only, semua response
         * WAJIB berformat JSON. Tidak ada kondisi if-guard yang menyebabkan
         * silent-fail atau redirect ke route('login').
         */

        // 401 — Belum login / token expired
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
                'data' => null,
                'errors' => null,
            ], 401);
        });

        // 403 — Tidak punya izin (role tidak sesuai)
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengakses resource ini.',
                'data' => null,
                'errors' => null,
            ], 403);
        });

        // 403 — Spatie UnauthorizedException (role/permission mismatch)
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengakses resource ini.',
                'data' => null,
                'errors' => null,
            ], 403);
        });

        // 404 — Route atau Model tidak ditemukan
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Resource yang diminta tidak ditemukan.',
                'data' => null,
                'errors' => null,
            ], 404);
        });

        // 404 — Model::findOrFail() gagal
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            $modelClass = class_basename($e->getModel());
            return response()->json([
                'success' => false,
                'message' => "Data {$modelClass} tidak ditemukan.",
                'data' => null,
                'errors' => null,
            ], 404);
        });

        // 405 — Method HTTP tidak diizinkan
        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Method HTTP tidak diizinkan untuk endpoint ini.',
                'data' => null,
                'errors' => null,
            ], 405);
        });

        // 422 — Validasi gagal (FormRequest)
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal, periksa kembali input Anda.',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        });

        // 500 — Error tak terduga (catch-all untuk API)
        $exceptions->render(function (\Throwable $e, $request) {
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
        });
    })->create();