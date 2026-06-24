<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model Notification — Custom Eloquent model.
 *
 * TIDAK menggunakan Illuminate\Notifications\DatabaseNotification bawaan Laravel.
 * Menggunakan custom table dengan kolom eksplisit untuk query & index efisien.
 */
class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type'     => NotificationType::class,
            'category' => NotificationCategory::class,
            'read_at'  => 'datetime',
        ];
    }

    // ========== SCOPES ==========

    /**
     * Scope: hanya notifikasi yang belum dibaca.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: filter berdasarkan user tertentu.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ========== RELASI ==========

    /**
     * User pemilik notifikasi ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== HELPERS ==========

    /**
     * Tandai notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Cek apakah notifikasi sudah dibaca.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
