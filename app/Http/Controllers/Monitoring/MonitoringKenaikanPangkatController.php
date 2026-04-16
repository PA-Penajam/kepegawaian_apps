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
        $perPage = $request->integer('per_page', 15);

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList'    => $service->getUpcomingKenaikanPangkat($periode, $perPage),
            'selectedPeriode'=> $periode,
            'kpStats'        => $service->getKpStats($periode),
        ]);
    }
}
