<?php

namespace App\Enums;

/**
 * Tipe Notifikasi — menentukan WARNA & ICON di Frontend.
 *
 * FE cukup mapping 4 nilai ini ke warna:
 * - success → Hijau
 * - warning → Kuning
 * - error   → Merah
 * - info    → Biru
 *
 * Enum ini stabil dan jarang berubah.
 */
enum NotificationType: string
{
    case SUCCESS = 'success';   // ✅ Disetujui, sync berhasil
    case WARNING = 'warning';   // ⚠️ Perlu perhatian, pending review
    case ERROR   = 'error';     // ❌ Ditolak, sync gagal, system error
    case INFO    = 'info';      // ℹ️ Informasi umum, pengajuan terkirim
}
