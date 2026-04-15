<?php

namespace App\Services\Prestasi;

/**
 * Service Layer untuk logika bisnis Prestasi Mandiri.
 * Menangani: create, update, delete, state validation.
 *
 * Arsitektur: Thin Controller → Service → Model (Arsitektur_Backend.md §2).
 */
class PrestasiService
{
    // TODO: Implementasi logika bisnis
    // - createPrestasi($data, $userId): Buat record + attach pivot mahasiswa/dosen
    // - updatePrestasi($id, $data, $userId): Update hanya jika PENDING/REJECTED
    // - deletePrestasi($id, $userId): Soft delete hanya jika PENDING
    // - getByUser($userId, $perPage): Paginated list milik user
    // - getDetail($id): Detail dengan relasi mahasiswa & dosen
}
