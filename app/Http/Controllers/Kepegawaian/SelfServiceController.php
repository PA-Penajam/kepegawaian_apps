<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SelfServiceController extends Controller
{
    public function __construct(
        private KgbMonitoringService $kgbService,
        private KenaikanPangkatMonitoringService $kpService,
    ) {}

    public function index(Request $request): Response
    {
        $pegawai = $this->currentPegawai($request, $this->indexRelations());

        return Inertia::render('self-service/index', [
            'pegawai' => $pegawai,
            'kgbInfo' => $this->resolveKgbInfo($pegawai),
            'kpInfo' => $this->resolveKpInfo($pegawai),
        ]);
    }

    public function detail(Request $request): Response
    {
        $pegawai = $this->currentPegawai($request, $this->detailRelations());

        return Inertia::render('self-service/detail', [
            'pegawai' => $pegawai,
        ]);
    }

    public function unlinked(): Response
    {
        return Inertia::render('self-service/unlinked');
    }

    private function currentPegawai(Request $request, array $relations): Pegawai
    {
        return $request->user()->pegawai()->with($relations)->firstOrFail();
    }

    private function indexRelations(): array
    {
        return [
            'pangkat',
            'jabatan',
            'unitKerja',
            'riwayatPangkat' => fn ($query) => $query
                ->aktif()
                ->with('pangkat')
                ->latest('tmt'),
        ];
    }

    private function detailRelations(): array
    {
        return [
            'pangkat',
            'jabatan',
            'unitKerja',
            'user',
            'riwayatPangkat.pangkat',
            'riwayatJabatan.jabatan',
            'riwayatJabatan.unitKerja',
            'riwayatPendidikan',
            'riwayatDiklat.jenisDiklat',
            'keluarga',
            'penghargaan.jenisPenghargaan',
            'hukumanDisiplin.jenisHukumanDisiplin',
            'dokumenPegawai',
        ];
    }

    private function resolveKgbInfo(Pegawai $pegawai): ?array
    {
        return $this->kgbService
            ->getUpcomingKgb(12)
            ->firstWhere('id', $pegawai->id);
    }

    private function resolveKpInfo(Pegawai $pegawai): ?array
    {
        return $this->kpService
            ->getUpcomingKenaikanPangkat()
            ->firstWhere('id', $pegawai->id);
    }
}
