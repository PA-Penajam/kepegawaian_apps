<?php

namespace App\Http\Controllers\Monitoring;

use App\Exports\KgbMonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonitoringKgbController extends Controller
{
    public function __construct(
        protected KgbMonitoringService $kgbMonitoringService,
    ) {}

    public function index(Request $request): Response
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rawUnitKerja = $request->input('unit_kerja');
        $unitKerja = is_string($rawUnitKerja) && trim($rawUnitKerja) !== '' ? trim($rawUnitKerja) : null;
        $rawGolongan = $request->input('golongan');
        $golongan = is_string($rawGolongan) && trim($rawGolongan) !== '' ? trim($rawGolongan) : null;
        $rawStatus = $request->input('status');
        $status = is_string($rawStatus) && trim($rawStatus) !== '' ? trim($rawStatus) : null;

        return Inertia::render('kepegawaian/monitoring/kgb/index', [
            'pegawaiList' => $this->kgbMonitoringService->getUpcomingKgb(3, $perPage, $unitKerja, $golongan, $status),
            'kgbStats' => $this->kgbMonitoringService->getKgbStats(3, $unitKerja, $golongan),
            'filters' => [
                'unit_kerja' => $unitKerja,
                'golongan' => $golongan,
                'status' => $status,
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
        $rawUnitKerja = $request->input('unit_kerja');
        $unitKerja = is_string($rawUnitKerja) && trim($rawUnitKerja) !== '' ? trim($rawUnitKerja) : null;
        $rawGolongan = $request->input('golongan');
        $golongan = is_string($rawGolongan) && trim($rawGolongan) !== '' ? trim($rawGolongan) : null;
        $rawStatus = $request->input('status');
        $status = is_string($rawStatus) && trim($rawStatus) !== '' ? trim($rawStatus) : null;

        return Excel::download(
            new KgbMonitoringExport($unitKerja, $golongan, $status),
            'kgb-monitoring.xlsx'
        );
    }
}
