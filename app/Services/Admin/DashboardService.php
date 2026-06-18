<?php

namespace App\Services\Admin;

use App\Models\PrestasiMandiri;
use App\Models\Rekognisi;
use App\Models\Sertifikasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service Layer — Dashboard Admin.
 * Aggregasi statistik, approval rates, trends, dan recent activities.
 */
class DashboardService
{
    /**
     * Ambil seluruh data ringkasan dashboard.
     */
    public function getDashboardData(): array
    {
        return [
            'stats' => $this->getStatistics(),
            'approval_rates' => $this->getApprovalRates(),
            'trends' => $this->getSubmissionTrends(),
            'recent_activities' => $this->getRecentActivities(),
        ];
    }

    /**
     * Kartu statistik (Kategori & Status).
     */
    private function getStatistics(): array
    {
        $totalPrestasi = PrestasiMandiri::count();
        $totalSertifikasi = Sertifikasi::count();
        $totalRekognisi = Rekognisi::count();

        // Hitung akumulasi status pengajuan dari ke-3 tabel sekaligus
        $pendingCount = PrestasiMandiri::where('status_internal', 'PENDING')->count()
            + Sertifikasi::where('status_internal', 'PENDING')->count()
            + Rekognisi::where('status_internal', 'PENDING')->count();

        $approvedCount = PrestasiMandiri::where('status_internal', 'like', 'APPROVED%')->count()
            + Sertifikasi::where('status_internal', 'like', 'APPROVED%')->count()
            + Rekognisi::where('status_internal', 'like', 'APPROVED%')->count();

        $rejectedCount = PrestasiMandiri::where('status_internal', 'REJECTED')->count()
            + Sertifikasi::where('status_internal', 'REJECTED')->count()
            + Rekognisi::where('status_internal', 'REJECTED')->count();

        return [
            'total_prestasi' => $totalPrestasi,
            'total_sertifikasi' => $totalSertifikasi,
            'total_rekognisi' => $totalRekognisi,
            'status_pending' => $pendingCount,
            'status_approved' => $approvedCount,
            'status_rejected' => $rejectedCount,
            'trend_prestasi' => '+0%',
            'trend_sertifikasi' => '+0%',
            'trend_rekognisi' => '+0%',
        ];
    }

    /**
     * Data graph: Approval Rates (Lengkap 3 Kategori).
     */
    private function getApprovalRates(): array
    {
        $approvedPrestasi = PrestasiMandiri::where('status_internal', 'like', 'APPROVED%')->count();
        $rejectedPrestasi = PrestasiMandiri::where('status_internal', 'REJECTED')->count();

        $approvedSertifikasi = Sertifikasi::where('status_internal', 'like', 'APPROVED%')->count();
        $rejectedSertifikasi = Sertifikasi::where('status_internal', 'REJECTED')->count();

        $approvedRekognisi = Rekognisi::where('status_internal', 'like', 'APPROVED%')->count();
        $rejectedRekognisi = Rekognisi::where('status_internal', 'REJECTED')->count();

        return [
            ['category' => 'Prestasi', 'approved' => $approvedPrestasi, 'rejected' => $rejectedPrestasi],
            ['category' => 'Sertifikasi', 'approved' => $approvedSertifikasi, 'rejected' => $rejectedSertifikasi],
            ['category' => 'Rekognisi', 'approved' => $approvedRekognisi, 'rejected' => $rejectedRekognisi],
        ];
    }

    /**
     * Data graph: Submission Trends (Dinamis 6 Bulan Terakhir).
     */
    private function getSubmissionTrends(): array
    {
        $trends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthName = $monthDate->translatedFormat('M'); // Mengambil nama bulan singkat (Jan, Feb, Mrt, dst)
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

            // Gabungkan total submit bulan tersebut dari ketiga jenis pengajuan
            $monthlyCount = PrestasiMandiri::whereBetween('created_at', [$start, $end])->count()
                + Sertifikasi::whereBetween('created_at', [$start, $end])->count()
                + Rekognisi::whereBetween('created_at', [$start, $end])->count();

            $trends[] = [
                'month' => $monthName,
                'submissions' => $monthlyCount,
            ];
        }

        return $trends;
    }

    /**
     * Data list: Recent Activities (Dinamis dari table activity_log).
     */
    private function getRecentActivities(): array
    {
        $recentActivities = [];
        if (Schema::hasTable('activity_log')) {
            $logs = DB::table('activity_log')
                ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
                ->select('activity_log.*', 'users.name as user_name')
                ->latest('activity_log.id')
                ->take(5)
                ->get();

            foreach ($logs as $log) {
                $recentActivities[] = [
                    'id' => $log->id,
                    'user_name' => $log->user_name ?? 'System',
                    'action' => $log->event ?? 'updated', // Berisi 'created', 'updated', atau 'deleted'
                    'target' => $log->description ?? 'Melakukan modifikasi pengajuan',
                    'created_at' => $log->created_at,
                ];
            }
        }

        return $recentActivities;
    }
}
