<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event yang di-broadcast ke Laravel Reverb saat notifikasi baru dibuat.
 *
 * FE (Next.js + Laravel Echo) listen ke:
 *   Echo.private(`notifications.${userId}`).listen('.notification.new', callback)
 *
 * Event ini implements ShouldBroadcast sehingga dikirim via queue worker,
 * tidak blocking request utama.
 */
class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly Notification $notification
    ) {}

    /**
     * Channel tujuan broadcast — private per user.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->notification->user_id),
        ];
    }

    /**
     * Nama event custom — FE listen ke '.notification.new'.
     * Pakai dot-prefix di Echo agar tidak di-prepend namespace.
     */
    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    /**
     * Data yang dikirim ke FE via WebSocket.
     * Format identik dengan NotificationResource untuk konsistensi.
     */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'type'       => $this->notification->type->value,
            'category'   => $this->notification->category->value,
            'title'      => $this->notification->title,
            'message'    => $this->notification->message,
            'action_url' => $this->notification->action_url,
            'is_read'    => false,
            'created_at' => $this->notification->created_at->toISOString(),
        ];
    }
}
