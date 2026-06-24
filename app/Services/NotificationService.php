<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Events\NotificationSent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service Layer — Notification Factory & Business Logic.
 *
 * SEMUA notifikasi dibuat melalui service ini (terpusat).
 * Tidak ada Notification::create() yang tersebar di controller/service lain.
 *
 * Tanggung jawab:
 * 1. Factory methods untuk setiap use case notifikasi
 * 2. Throttle mechanism untuk queue alerts (5 menit)
 * 3. Insert DB + dispatch broadcast event
 */
class NotificationService
{
    // ========================================================================
    // MAHASISWA NOTIFICATIONS
    // ========================================================================

    /**
     * Notifikasi: Pengajuan berhasil dikirim.
     * Trigger: Mahasiswa klik submit di PrestasiService/SertifikasiService/RekognisiService::create().
     *
     * @param Model $record Record pengajuan (PrestasiMandiri, Sertifikasi, Rekognisi)
     * @param User $user Mahasiswa yang mengirim
     */
    public function submissionSent(Model $record, User $user): void
    {
        $tipe = $this->resolveRecordLabel($record);
        $nama = $this->resolveRecordName($record);

        $this->send(
            userId: $user->id,
            type: NotificationType::INFO,
            category: NotificationCategory::SUBMISSION_SENT,
            title: "{$tipe} Terkirim",
            message: "Pengajuan {$tipe} '{$nama}' berhasil dikirim dan menunggu verifikasi admin.",
            actionUrl: $this->resolveActionUrl($record, 'mahasiswa'),
        );
    }

    /**
     * Notifikasi: Pengajuan disetujui oleh admin.
     * Trigger: VerifikasiService::processVerification() status APPROVE.
     *
     * @param Model $record Record pengajuan yang di-approve
     */
    public function submissionApproved(Model $record): void
    {
        $tipe = $this->resolveRecordLabel($record);
        $nama = $this->resolveRecordName($record);
        $creatorId = $record->created_by;

        if (!$creatorId) {
            Log::warning('submissionApproved: created_by is null', ['record' => $record->id]);
            return;
        }

        $this->send(
            userId: $creatorId,
            type: NotificationType::SUCCESS,
            category: NotificationCategory::SUBMISSION_APPROVED,
            title: "{$tipe} Disetujui",
            message: "{$tipe} '{$nama}' telah disetujui oleh admin.",
            actionUrl: $this->resolveActionUrl($record, 'mahasiswa'),
        );
    }

    /**
     * Notifikasi: Pengajuan ditolak oleh admin.
     * Trigger: VerifikasiService::processVerification() status REJECT.
     *
     * @param Model $record Record pengajuan yang ditolak
     * @param string|null $alasan Alasan penolakan dari admin
     */
    public function submissionRejected(Model $record, ?string $alasan = null): void
    {
        $tipe = $this->resolveRecordLabel($record);
        $nama = $this->resolveRecordName($record);
        $creatorId = $record->created_by;

        if (!$creatorId) {
            Log::warning('submissionRejected: created_by is null', ['record' => $record->id]);
            return;
        }

        $message = "{$tipe} '{$nama}' ditolak oleh admin.";
        if ($alasan) {
            $message .= " Alasan: {$alasan}";
        }

        $this->send(
            userId: $creatorId,
            type: NotificationType::ERROR,
            category: NotificationCategory::SUBMISSION_REJECTED,
            title: "{$tipe} Ditolak",
            message: $message,
            actionUrl: $this->resolveActionUrl($record, 'mahasiswa'),
        );
    }

    // ========================================================================
    // ADMIN NOTIFICATIONS
    // ========================================================================

    /**
     * Notifikasi: Mahasiswa mengirim ulang revisi setelah ditolak.
     * Trigger: PrestasiService/SertifikasiService/RekognisiService::update() saat REJECTED → PENDING.
     *
     * Dikirim ke admin yang sebelumnya menolak (approved_by), bukan semua admin.
     *
     * @param Model $record Record pengajuan yang di-resubmit
     */
    public function revisionResubmitted(Model $record): void
    {
        $adminId = $record->approved_by;

        if (!$adminId) {
            Log::warning('revisionResubmitted: approved_by is null', ['record' => $record->id]);
            return;
        }

        $tipe = $this->resolveRecordLabel($record);
        $nama = $this->resolveRecordName($record);

        $this->send(
            userId: $adminId,
            type: NotificationType::WARNING,
            category: NotificationCategory::REVISION_RESUBMITTED,
            title: 'Revisi Dikirim Ulang',
            message: "Mahasiswa telah memperbaiki dan mengirim ulang {$tipe} '{$nama}'. Silakan review kembali.",
            actionUrl: $this->resolveActionUrl($record, 'admin'),
        );
    }

    /**
     * Notifikasi: Sync queue mengalami kegagalan.
     * Trigger: SyncToKemdikbudJob saat max retry tercapai.
     *
     * THROTTLED: Maksimal 1 notifikasi per 5 menit untuk mencegah spam.
     * Dikirim ke semua Admin dan Superadmin.
     *
     * @param string $message Pesan error
     * @param int $failCount Jumlah total item yang gagal
     */
    public function queueAlert(string $message, int $failCount): void
    {
        $throttleKey = 'notif_throttle:queue_alert';

        if (Cache::has($throttleKey)) {
            return;
        }

        $recipients = User::role(['admin', 'superadmin'])->get();

        foreach ($recipients as $user) {
            $this->send(
                userId: $user->id,
                type: NotificationType::ERROR,
                category: NotificationCategory::QUEUE_ALERT,
                title: 'Sync Queue: Kegagalan Terdeteksi',
                message: "{$failCount} item gagal sinkronisasi. {$message}",
                actionUrl: '/admin/sync-queue',
            );
        }

        Cache::put($throttleKey, true, now()->addMinutes(5));
    }

    // ========================================================================
    // SUPERADMIN NOTIFICATIONS
    // ========================================================================

    /**
     * Notifikasi: Error kritis pada sistem.
     * Trigger: SyncToKemdikbudJob saat auth failure (queue auto-pause).
     *
     * THROTTLED: Maksimal 1 notifikasi per 5 menit per kategori.
     * Dikirim ke semua Superadmin.
     *
     * @param string $title Judul peringatan
     * @param string $message Detail peringatan
     */
    public function systemAlert(string $title, string $message): void
    {
        $throttleKey = 'notif_throttle:system_alert';

        if (Cache::has($throttleKey)) {
            return;
        }

        $superadmins = User::role('superadmin')->get();

        foreach ($superadmins as $user) {
            $this->send(
                userId: $user->id,
                type: NotificationType::ERROR,
                category: NotificationCategory::SYSTEM_ALERT,
                title: $title,
                message: $message,
                actionUrl: '/superadmin/sync-queue',
            );
        }

        Cache::put($throttleKey, true, now()->addMinutes(5));
    }

    /**
     * Notifikasi: Queue monitoring issue untuk superadmin.
     * Trigger: Bisa dipanggil dari scheduled command atau health check.
     *
     * THROTTLED: Maksimal 1 notifikasi per 5 menit.
     * Dikirim ke semua Superadmin.
     */
    public function queueMonitor(string $title, string $message): void
    {
        $throttleKey = 'notif_throttle:queue_monitor';

        if (Cache::has($throttleKey)) {
            return;
        }

        $superadmins = User::role('superadmin')->get();

        foreach ($superadmins as $user) {
            $this->send(
                userId: $user->id,
                type: NotificationType::WARNING,
                category: NotificationCategory::QUEUE_MONITOR,
                title: $title,
                message: $message,
                actionUrl: '/superadmin/sync-queue',
            );
        }

        Cache::put($throttleKey, true, now()->addMinutes(5));
    }

    // ========================================================================
    // CORE SEND METHOD
    // ========================================================================

    /**
     * Insert notifikasi ke DB dan broadcast via Reverb.
     * Semua factory method di atas memanggil method ini.
     */
    private function send(
        string $userId,
        NotificationType $type,
        NotificationCategory $category,
        string $title,
        string $message,
        ?string $actionUrl = null,
    ): Notification {
        $notification = Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'category'   => $category,
            'title'      => $title,
            'message'    => $message,
            'action_url' => $actionUrl,
        ]);

        // Broadcast ke Reverb (via queue worker, non-blocking)
        event(new NotificationSent($notification));

        return $notification;
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    /**
     * Resolve label tipe kegiatan dari model class.
     */
    private function resolveRecordLabel(Model $record): string
    {
        return match (get_class($record)) {
            \App\Models\PrestasiMandiri::class => 'Prestasi',
            \App\Models\Sertifikasi::class     => 'Sertifikasi',
            \App\Models\Rekognisi::class        => 'Rekognisi',
            default                             => 'Pengajuan',
        };
    }

    /**
     * Resolve nama/judul kegiatan dari record.
     * Prestasi menggunakan 'lomba', Sertifikasi & Rekognisi menggunakan 'nama'.
     */
    private function resolveRecordName(Model $record): string
    {
        if ($record instanceof \App\Models\PrestasiMandiri) {
            return $record->lomba ?? 'Tanpa Judul';
        }

        return $record->nama ?? 'Tanpa Judul';
    }

    /**
     * Resolve action URL berdasarkan tipe record dan role.
     * Menggunakan relative path agar FE bisa prepend baseUrl sendiri.
     */
    private function resolveActionUrl(Model $record, string $role): string
    {
        $type = match (get_class($record)) {
            \App\Models\PrestasiMandiri::class => 'prestasi',
            \App\Models\Sertifikasi::class     => 'sertifikasi',
            \App\Models\Rekognisi::class        => 'rekognisi',
            default                             => 'unknown',
        };

        return match ($role) {
            'mahasiswa' => "/mahasiswa/{$type}/{$record->id}",
            'admin'     => "/admin/verifikasi/{$type}/{$record->id}",
            default     => "/{$type}/{$record->id}",
        };
    }
}
