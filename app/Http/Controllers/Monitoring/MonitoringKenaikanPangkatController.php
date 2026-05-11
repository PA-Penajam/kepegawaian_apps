<?php

namespace App\Http\Controllers\Monitoring;

use App\Exports\KenaikanPangkatMonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonitoringKenaikanPangkatController extends Controller
{
    public function index(Request $request, KenaikanPangkatMonitoringService $service): Response
    {
        $periodeBulan = $request->integer('periode_bulan') ?: null;
        $periodeTahun = $request->integer('periode_tahun') ?: null;
        $perPage = $request->integer('per_page', 15);
        $unitKerja = $request->string('unit_kerja')->value() ?: null;
        $golongan = $request->string('golongan')->value() ?: null;

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList' => $service->getUpcomingKenaikanPangkat($periodeBulan, $perPage, $unitKerja, $golongan, $periodeTahun),
            'selectedPeriode' => $periodeBulan,
            'periodeBulanan' => $service->getAllPeriodeBulanan($periodeTahun ?? now()->year),
            'kpStats' => $service->getKpStats($periodeBulan, $periodeTahun, $unitKerja, $golongan),
            'filters' => [
                'unit_kerja' => $unitKerja,
                'golongan' => $golongan,
                'periode_bulan' => $periodeBulan,
                'periode_tahun' => $periodeTahun,
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

    public function export(Request $request): BinaryFileResponse
    {
        $periodeBulan = $request->integer('periode_bulan') ?: null;
        $periodeTahun = $request->integer('periode_tahun') ?: null;
        $unitKerja = $request->string('unit_kerja')->value() ?: null;
        $golongan = $request->string('golongan')->value() ?: null;

        return Excel::download(
            new KenaikanPangkatMonitoringExport($periodeBulan, $periodeTahun, $unitKerja, $golongan),
            'kp-monitoring.xlsx'
        );
    }
}
