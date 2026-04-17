<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKenaikanPangkatController extends Controller
{
    public function index(Request $request, KenaikanPangkatMonitoringService $service): Response
    {
        $periode    = $request->string('periode')->value() ?: null;
        $perPage    = $request->integer('per_page', 15);
        $unitKerja  = $request->string('unit_kerja')->value() ?: null;
        $golongan   = $request->string('golongan')->value() ?: null;

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList'     => $service->getUpcomingKenaikanPangkat($periode, $perPage, $unitKerja, $golongan),
            'selectedPeriode' => $periode,
            'kpStats'         => $service->getKpStats($periode, $unitKerja, $golongan),
            'filters'         => [
                'unit_kerja' => $unitKerja,
                'golongan'   => $golongan,
                'periode'    => $periode,
            ],
            'filterOptions'   => [
                'unitKerja' => RefUnitKerja::query()
                    ->select(['id', 'nama'])
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(),
                'golongan' => RefPangkat::query()
                    ->selectRaw('DISTINCT golongan')
                    ->whereNotNull('golongan')
                    ->orderBy('golongan')
                    ->pluck('golongan'),
            ],
        ]);
    }
}
