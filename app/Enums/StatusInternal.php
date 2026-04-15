<?php

namespace App\Enums;

/**
 * Status Internal Udinus — State Machine untuk alur approval & sinkronisasi.
 *
 * PENDING → REJECTED (Admin tolak) → PENDING (Mahasiswa edit ulang)
 * PENDING → APPROVED_UNSYNCED (Admin approve) → SYNC_SUCCESS / SYNC_FAILED
 * SYNC_FAILED → SYNC_SUCCESS (Auto-retry worker berhasil)
 */
enum StatusInternal: string
{
    case PENDING = 'PENDING';
    case REJECTED = 'REJECTED';
    case APPROVED_UNSYNCED = 'APPROVED_UNSYNCED';
    case SYNC_SUCCESS = 'SYNC_SUCCESS';
    case SYNC_FAILED = 'SYNC_FAILED';

    /**
     * Cek apakah status ini mengunci data (read-only bagi Mahasiswa).
     */
    public function isLockedForMahasiswa(): bool
    {
        return in_array($this, [
            self::APPROVED_UNSYNCED,
            self::SYNC_SUCCESS,
            self::SYNC_FAILED,
        ]);
    }

    /**
     * Cek apakah status ini adalah KUNCI MATI total (read-only bagi semua, termasuk Superadmin).
     */
    public function isEternityLocked(): bool
    {
        return $this === self::SYNC_SUCCESS;
    }
}
