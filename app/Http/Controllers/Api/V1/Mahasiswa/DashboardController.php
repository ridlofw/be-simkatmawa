<?php

namespace App\Http\Controllers\Api\V1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\PrestasiMandiri;
use App\Models\Rekognisi;
use App\Models\Sertifikasi;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controller Dashboard Mahasiswa.
 * Menyediakan ringkasan statistik + aktivitas terbaru untuk beranda.
 */
class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * [GET] Dashboard Mahasiswa — Statistik + Aktivitas Terbaru.
     *
     * Response:
     * - total_prestasi: jumlah prestasi yang mahasiswa ikuti
     * - total_sertifikasi: jumlah sertifikasi yang mahasiswa ikuti
     * - total_rekognisi: jumlah rekognisi yang mahasiswa ikuti
     * - aktivitas_terbaru: 10 pengajuan terbaru (gabungan semua tipe)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $nim = $user->mahasiswa?->nim;

        if (!$nim) {
            return $this->errorResponse('Data mahasiswa tidak ditemukan untuk akun ini.', 404);
        }

        // Set locale untuk Carbon diffForHumans() → "2 jam yang lalu"
        Carbon::setLocale('id');

        // ===== Hitung Total per Tipe =====
        $totalPrestasi = PrestasiMandiri::whereHas('mahasiswa', fn($q) => $q->where('mahasiswa.nim', $nim))->count();
        $totalSertifikasi = Sertifikasi::whereHas('mahasiswa', fn($q) => $q->where('mahasiswa.nim', $nim))->count();
        $totalRekognisi = Rekognisi::whereHas('mahasiswa', fn($q) => $q->where('mahasiswa.nim', $nim))->count();

        // ===== Aktivitas Terbaru (gabungan 3 tipe, limit 10) =====
        $prestasi = PrestasiMandiri::whereHas('mahasiswa', fn($q) => $q->where('mahasiswa.nim', $nim))
            ->select('id', 'lomba as judul', 'status_internal', 'created_at')
            ->get()
            ->map(fn($item) => $this->formatActivity($item, 'Prestasi Mandiri'));

        $sertifikasi = Sertifikasi::whereHas('mahasiswa', fn($q) => $q->where('mahasiswa.nim', $nim))
            ->select('id', 'nama as judul', 'status_internal', 'created_at')
            ->get()
            ->map(fn($item) => $this->formatActivity($item, 'Sertifikasi'));

        $rekognisi = Rekognisi::whereHas('mahasiswa', fn($q) => $q->where('mahasiswa.nim', $nim))
            ->select('id', 'nama as judul', 'status_internal', 'created_at')
            ->get()
            ->map(fn($item) => $this->formatActivity($item, 'Rekognisi'));

        // Gabung, sort by waktu terbaru, ambil 10
        $aktivitasTerbaru = $prestasi
            ->concat($sertifikasi)
            ->concat($rekognisi)
            ->sortByDesc('waktu_raw')
            ->take(10)
            ->values();

        return $this->successResponse([
            'total_prestasi' => $totalPrestasi,
            'total_sertifikasi' => $totalSertifikasi,
            'total_rekognisi' => $totalRekognisi,
            'aktivitas_terbaru' => $aktivitasTerbaru,
        ], 'Data dashboard berhasil diambil.');
    }

    /**
     * Format item aktivitas untuk response dashboard.
     * Status di-mapping ke label human-readable.
     * Waktu di-format sebagai relative time (Carbon diffForHumans).
     */
    private function formatActivity(mixed $item, string $kategori): array
    {
        // Mapping status_internal → label frontend
        $statusLabel = match ($item->status_internal?->value ?? $item->status_internal) {
            'PENDING' => 'Menunggu',
            'APPROVED_UNSYNCED', 'SYNC_SUCCESS', 'SYNC_FAILED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            default => $item->status_internal?->value ?? '-',
        };

        return [
            'id' => $item->id,
            'judul' => $item->judul,
            'kategori' => $kategori,
            'status' => $statusLabel,
            'waktu' => $item->created_at?->diffForHumans(),      // "2 jam yang lalu", "kemarin"
            'waktu_raw' => $item->created_at?->toISOString(),     // ISO 8601 untuk sorting frontend
        ];
    }
}
