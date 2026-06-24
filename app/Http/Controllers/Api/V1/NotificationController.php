<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller — Notification REST API.
 *
 * Semua endpoint di-scope otomatis ke user yang sedang login (auth()->id()).
 * Mahasiswa, Admin, Superadmin menggunakan endpoint yang sama —
 * tanpa prefix per role — karena notifikasi sudah di-scope per user_id.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     *
     * Daftar notifikasi user (paginated).
     * Query params: ?unread_only=true&limit=15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Notification::forUser(auth()->id())
            ->orderByDesc('created_at');

        // Filter: hanya yang belum dibaca
        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $limit = min((int) $request->input('limit', 15), 50);

        return NotificationResource::collection(
            $query->paginate($limit)
        );
    }

    /**
     * GET /api/v1/notifications/unread-count
     *
     * Jumlah notifikasi yang belum dibaca.
     * Digunakan FE untuk menampilkan badge angka di ikon lonceng.
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::forUser(auth()->id())
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     *
     * Tandai 1 notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = Notification::forUser(auth()->id())->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notifikasi ditandai sebagai dibaca.',
            'data'    => new NotificationResource($notification),
        ]);
    }

    /**
     * PATCH /api/v1/notifications/read-all
     *
     * Tandai semua notifikasi user sebagai sudah dibaca.
     */
    public function markAllAsRead(): JsonResponse
    {
        $updated = Notification::forUser(auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => "Semua notifikasi ditandai sebagai dibaca.",
            'updated' => $updated,
        ]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     *
     * Hapus permanen 1 notifikasi.
     * Hard delete — notifikasi bukan data bisnis, tidak perlu recycle bin.
     */
    public function destroy(string $id): JsonResponse
    {
        $notification = Notification::forUser(auth()->id())->findOrFail($id);

        $notification->delete();

        return response()->json([
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }
}
