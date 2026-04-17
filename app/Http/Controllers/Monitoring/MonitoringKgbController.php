<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKgbController extends Controller
{
    public function __construct(
        protected KgbMonitoringService $kgbMonitoringService,
    ) {}

    public function index(Request $request): Response
    {
        $perPage    = $request->integer('per_page', 15);
        $unitKerja  = $request->string('unit_kerja')->value() ?: null;
        $golongan   = $request->string('golongan')->value() ?: null;
        $status     = $request->string('status')->value() ?: null;

        return Inertia::render('kepegawaian/monitoring/kgb/index', [
            'pegawaiList' => $this->kgbMonitoringService->getUpcomingKgb(3, $perPage, $unitKerja, $golongan, $status),
            'kgbStats'    => $this->kgbMonitoringService->getKgbStats(3, $unitKerja, $golongan),
            'filters'     => [
                'unit_kerja' => $unitKerja,
                'golongan'   => $golongan,
                'status'     => $status,
            ],
            'filterOptions' => [
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
