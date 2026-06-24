<?php

namespace App\Enums;

/**
 * Kategori Notifikasi — menentukan KONTEKS BISNIS.
 *
 * Berbeda dengan NotificationType (warna), enum ini menjelaskan
 * "apa yang terjadi" secara spesifik. FE bisa pakai untuk icon
 * atau grouping. Bisa bertambah seiring fitur baru.
 */
enum NotificationCategory: string
{
    // === Mahasiswa ===
    case SUBMISSION_SENT       = 'submission_sent';       // Pengajuan berhasil dikirim
    case SUBMISSION_APPROVED   = 'submission_approved';   // Pengajuan disetujui admin
    case SUBMISSION_REJECTED   = 'submission_rejected';   // Pengajuan ditolak admin

    // === Admin ===
    case REVISION_RESUBMITTED  = 'revision_resubmitted';  // Mahasiswa resubmit setelah ditolak
    case QUEUE_ALERT           = 'queue_alert';            // Sync queue gagal (throttled 5 menit)

    // === Superadmin ===
    case SYSTEM_ALERT          = 'system_alert';           // Auth failure, critical error
    case QUEUE_MONITOR         = 'queue_monitor';          // Sync queue issue (throttled 5 menit)

    /**
     * Label human-readable untuk tampilan.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUBMISSION_SENT       => 'Pengajuan Terkirim',
            self::SUBMISSION_APPROVED   => 'Pengajuan Disetujui',
            self::SUBMISSION_REJECTED   => 'Pengajuan Ditolak',
            self::REVISION_RESUBMITTED  => 'Revisi Dikirim Ulang',
            self::QUEUE_ALERT           => 'Peringatan Antrean',
            self::SYSTEM_ALERT          => 'Peringatan Sistem',
            self::QUEUE_MONITOR         => 'Monitoring Antrean',
        };
    }
}
