<?php

namespace App\Services\ActivityLog;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * Service Layer — Activity Log.
 * Shared service untuk Mahasiswa dan Admin activity log endpoints.
 */
class ActivityLogService
{
    /**
     * Ambil daftar aktivitas milik user tertentu (scope: mahasiswa).
     */
    public function getUserLogs(User $user, int $perPage, ?string $search): LengthAwarePaginator
    {
        $query = Activity::with('causer')
            ->where('causer_id', $user->id)
            ->where('causer_type', get_class($user))
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Ambil detail activity log milik user tertentu (scope: mahasiswa).
     */
    public function getUserLogDetail(User $user, int $id): ?Activity
    {
        return Activity::with(['causer', 'subject'])
            ->where('causer_id', $user->id)
            ->find($id);
    }

    /**
     * Ambil daftar semua aktivitas (scope: admin — semua user).
     */
    public function getAllLogs(
        int $perPage,
        ?string $search,
        ?string $causerId,
        ?string $causerType,
        ?string $event,
        ?string $modul
    ): LengthAwarePaginator {
        $query = Activity::with(['causer', 'subject'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }

        if ($causerId) {
            $query->where('causer_id', $causerId);
        }

        if ($causerType) {
            $query->where('causer_type', $causerType);
        }

        if ($event) {
            $query->where('event', $event);
        }

        if ($modul) {
            $query->where('log_name', $modul);
        }

        return $query->paginate($perPage);
    }

    /**
     * Ambil detail activity log (scope: admin — tanpa filter ownership).
     */
    public function getLogDetail(int $id): ?Activity
    {
        return Activity::with(['causer', 'subject'])->find($id);
    }
}
