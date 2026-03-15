<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKenaikanPangkatController extends Controller
{
    public function index(Request $request, KenaikanPangkatMonitoringService $service): Response
    {
        $periode = $request->string('periode')->toString();
        $periode = $periode !== '' ? $periode : null;

        $pegawaiList = $service->getUpcomingKenaikanPangkat($periode);

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList' => $pegawaiList,
            'selectedPeriode' => $periode,
            'kpStats' => [
                'total' => $pegawaiList->count(),
                'sudahEligible' => $pegawaiList->where('status', 'Sudah Eligible')->count(),
                'mendekatiEligible' => $pegawaiList->where('status', 'Mendekati Eligible')->count(),
                'belumEligible' => $pegawaiList->where('status', 'Belum Eligible')->count(),
            ],
        ]);
    }
}
