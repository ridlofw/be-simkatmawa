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
        // 1. Ambil agregat status dari ketiga tabel hanya dalam 3 query menggunakan Eloquent (menghindari salah nama tabel)
        $prestasiStats = PrestasiMandiri::select('status_internal', DB::raw('count(*) as total'))->groupBy('status_internal')->get();
        $sertifikasiStats = Sertifikasi::select('status_internal', DB::raw('count(*) as total'))->groupBy('status_internal')->get();
        $rekognisiStats = Rekognisi::select('status_internal', DB::raw('count(*) as total'))->groupBy('status_internal')->get();

        $statsData = [
            'prestasi' => $prestasiStats,
            'sertifikasi' => $sertifikasiStats,
            'rekognisi' => $rekognisiStats,
        ];

        return [
            'stats' => $this->getStatistics($statsData),
            'approval_rates' => $this->getApprovalRates($statsData),
            'trends' => $this->getSubmissionTrends(),
            'recent_activities' => $this->getRecentActivities(),
        ];
    }

    /**
     * Kartu statistik (Kategori & Status).
     */
    private function getStatistics(array $statsData): array
    {
        $sumStatus = function ($stats, $statusMatch) {
            $sum = 0;
            foreach ($stats as $stat) {
                // Di Laravel 11, cast Enum bisa jadi menghasilkan object Enum. Ambil value-nya jika iya.
                $statusVal = is_object($stat->status_internal) ? $stat->status_internal->value : $stat->status_internal;
                
                if (!$statusVal) continue;

                if ($statusMatch === 'PENDING' && $statusVal === 'PENDING') {
                    $sum += $stat->total;
                } elseif ($statusMatch === 'APPROVED' && str_starts_with($statusVal, 'APPROVED')) {
                    $sum += $stat->total;
                } elseif ($statusMatch === 'REJECTED' && $statusVal === 'REJECTED') {
                    $sum += $stat->total;
                }
            }
            return $sum;
        };

        $sumTotal = function ($stats) {
            $sum = 0;
            foreach ($stats as $stat) {
                $sum += $stat->total;
            }
            return $sum;
        };

        return [
            'total_prestasi' => $sumTotal($statsData['prestasi']),
            'total_sertifikasi' => $sumTotal($statsData['sertifikasi']),
            'total_rekognisi' => $sumTotal($statsData['rekognisi']),
            
            'status_pending' => $sumStatus($statsData['prestasi'], 'PENDING') 
                              + $sumStatus($statsData['sertifikasi'], 'PENDING') 
                              + $sumStatus($statsData['rekognisi'], 'PENDING'),
                              
            'status_approved' => $sumStatus($statsData['prestasi'], 'APPROVED') 
                               + $sumStatus($statsData['sertifikasi'], 'APPROVED') 
                               + $sumStatus($statsData['rekognisi'], 'APPROVED'),
                               
            'status_rejected' => $sumStatus($statsData['prestasi'], 'REJECTED') 
                               + $sumStatus($statsData['sertifikasi'], 'REJECTED') 
                               + $sumStatus($statsData['rekognisi'], 'REJECTED'),
                               
            'trend_prestasi' => '+0%',
            'trend_sertifikasi' => '+0%',
            'trend_rekognisi' => '+0%',
        ];
    }

    /**
     * Data graph: Approval Rates (Lengkap 3 Kategori).
     */
    private function getApprovalRates(array $statsData): array
    {
        $getRate = function ($stats) {
            $approved = 0;
            $rejected = 0;
            foreach ($stats as $stat) {
                $statusVal = is_object($stat->status_internal) ? $stat->status_internal->value : $stat->status_internal;
                
                if (!$statusVal) continue;

                if (str_starts_with($statusVal, 'APPROVED')) {
                    $approved += $stat->total;
                } elseif ($statusVal === 'REJECTED') {
                    $rejected += $stat->total;
                }
            }
            return ['approved' => $approved, 'rejected' => $rejected];
        };

        $prestasi = $getRate($statsData['prestasi']);
        $sertifikasi = $getRate($statsData['sertifikasi']);
        $rekognisi = $getRate($statsData['rekognisi']);

        return [
            ['category' => 'Prestasi', 'approved' => $prestasi['approved'], 'rejected' => $prestasi['rejected']],
            ['category' => 'Sertifikasi', 'approved' => $sertifikasi['approved'], 'rejected' => $sertifikasi['rejected']],
            ['category' => 'Rekognisi', 'approved' => $rekognisi['approved'], 'rejected' => $rekognisi['rejected']],
        ];
    }

    /**
     * Data graph: Submission Trends (Dinamis 6 Bulan Terakhir).
     */
    private function getSubmissionTrends(): array
    {
        $startDate = Carbon::now()->subMonths(5)->startOfMonth();
        
        // Optimasi: 3 Query menggunakan GROUP BY (mengatasi 18 query looping)
        $getTrends = function ($modelClass) use ($startDate) {
            return $modelClass::select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month_year"), DB::raw('count(*) as total'))
                ->where('created_at', '>=', $startDate)
                ->groupBy('month_year')
                ->pluck('total', 'month_year')
                ->toArray();
        };

        $prestasiCounts = $getTrends(PrestasiMandiri::class);
        $sertifikasiCounts = $getTrends(Sertifikasi::class);
        $rekognisiCounts = $getTrends(Rekognisi::class);

        $trends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthName = $monthDate->translatedFormat('M');
            $monthKey = $monthDate->format('Y-m');

            $monthlyCount = ($prestasiCounts[$monthKey] ?? 0)
                          + ($sertifikasiCounts[$monthKey] ?? 0)
                          + ($rekognisiCounts[$monthKey] ?? 0);

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
                    'action' => $log->event ?? 'updated', 
                    'target' => $log->description ?? 'Melakukan modifikasi pengajuan',
                    'created_at' => $log->created_at,
                ];
            }
        }

        return $recentActivities;
    }
}
