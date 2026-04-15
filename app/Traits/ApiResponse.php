<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait ApiResponse — Standardisasi format JSON untuk seluruh endpoint.
 *
 * Format mengikuti kontrak di Kontrak_API_Frontend.md §1:
 * { "success": bool, "message": string, "data": mixed, "errors": mixed }
 */
trait ApiResponse
{
    /**
     * Response sukses (HTTP 200).
     */
    protected function successResponse(mixed $data = null, string $message = 'Berhasil.', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $code);
    }

    /**
     * Response sukses created (HTTP 201).
     */
    protected function createdResponse(mixed $data = null, string $message = 'Data berhasil dibuat.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Response error generik.
     */
    protected function errorResponse(string $message = 'Terjadi kesalahan.', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Response validasi gagal (HTTP 422).
     */
    protected function validationErrorResponse(mixed $errors, string $message = 'Validasi gagal, periksa kembali input Anda.'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Response unauthorized (HTTP 401).
     */
    protected function unauthorizedResponse(string $message = 'Akses ditolak. Silakan login terlebih dahulu.'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Response forbidden (HTTP 403).
     */
    protected function forbiddenResponse(string $message = 'Anda tidak memiliki izin untuk mengakses resource ini.'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Response not found (HTTP 404).
     */
    protected function notFoundResponse(string $message = 'Data tidak ditemukan.'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }
}
