<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\KgbMonitoringService;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKgbController extends Controller
{
    public function __construct(
        protected KgbMonitoringService $kgbMonitoringService,
    ) {}

    public function index(): Response
    {
        $pegawaiList = $this->kgbMonitoringService->getUpcomingKgb();

        return Inertia::render('kepegawaian/monitoring/kgb/index', [
            'pegawaiList' => $pegawaiList->all(),
            'kgbStats' => [
                'total' => $pegawaiList->count(),
                'jatuhTempo' => $pegawaiList->where('status', 'Sudah Jatuh Tempo')->count(),
                'segera' => $pegawaiList->where('status', 'Segera')->count(),
                'mendekati' => $pegawaiList->where('status', 'Mendekati')->count(),
                'aman' => $pegawaiList->where('status', 'Aman')->count(),
            ],
        ]);
    }
}
