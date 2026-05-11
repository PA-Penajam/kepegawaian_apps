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
    public function __construct(private readonly KenaikanPangkatMonitoringService $service) {}

    public function index(Request $request): Response
    {
        22 | $periodeBulan = $request->integer('bulan') ?: ($request->integer('periode_bulan') ?: null);
        23 | $periodeTahun = $request->integer('tahun') ?: ($request->integer('periode_tahun') ?: null);
        $periodeTahun = $request->integer('tahun') ?: ($request->integer('periode_tahun') ?: now()->year);
        24 | $perPage = $request->input('per_page', 15);
        $unitKerja = $request->string('unit_kerja')->value() ?: null;
        $golongan = $request->string('golongan')->value() ?: null;
        $list = $this->service->getUpcomingKenaikanPangkat($periodeBulan, $perPage, $unitKerja, $golongan, $periodeTahun);
        $stats = $this->service->getKpStats($periodeBulan, $periodeTahun, $unitKerja, $golongan);

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList' => $list,
            'list' => $list,
            'selectedPeriode' => $periodeBulan,
            'periodeBulanan' => $this->service->getAllPeriodeBulanan($periodeTahun),
            'stats' => $stats,
            'kpStats' => $stats,
            'bulanOptions' => $this->bulanOptions(),
            'tahunOptions' => range(now()->year, now()->year + 3),
            'filters' => [
                'unit_kerja' => $unitKerja,
                'golongan' => $golongan,
                'bulan' => $periodeBulan,
                'tahun' => $periodeTahun,
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
        $periodeBulan = $request->integer('bulan') ?: ($request->integer('periode_bulan') ?: null);
        $periodeTahun = $request->integer('tahun') ?: ($request->integer('periode_tahun') ?: null);
        $unitKerja = $request->string('unit_kerja')->value() ?: null;
        $golongan = $request->string('golongan')->value() ?: null;

        return Excel::download(
            new KenaikanPangkatMonitoringExport($periodeBulan, $periodeTahun, $unitKerja, $golongan),
            'kp-monitoring.xlsx'
        );
    }

    private function bulanOptions(): array
    {
        return collect([
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ])->map(fn (string $label, int $value): array => [
            'value' => $value,
            'label' => $label,
        ])->values()->all();
    }
}
