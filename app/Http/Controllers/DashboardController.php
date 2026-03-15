<?php

namespace App\Http\Controllers;

use App\Enums\JenjangPendidikan;
use App\Models\Pegawai;
use App\Models\RefUnitKerja;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $totalAktif = Pegawai::aktif()->count();

        // Distribusi golongan (I, II, III, IV)
        $pegawaiDenganPangkat = Pegawai::aktif()
            ->with('pangkat')
            ->get();

        $distribusiGolongan = [
            'I' => 0,
            'II' => 0,
            'III' => 0,
            'IV' => 0,
        ];

        foreach ($pegawaiDenganPangkat as $pegawai) {
            if ($pegawai->pangkat && $pegawai->pangkat->kode) {
                $golongan = explode('/', $pegawai->pangkat->kode)[0];
                if (array_key_exists($golongan, $distribusiGolongan)) {
                    $distribusiGolongan[$golongan]++;
                }
            }
        }

        // Distribusi per unit kerja (top 6)
        $distribusiUnitKerja = RefUnitKerja::withCount(['pegawai' => fn ($q) => $q->aktif()])
            ->orderByDesc('pegawai_count')
            ->take(6)
            ->get()
            ->map(fn ($unit) => [
                'nama' => $unit->nama,
                'pegawai_count' => $unit->pegawai_count,
            ]);

        // Distribusi jenis kelamin
        $distribusiJenisKelamin = Pegawai::aktif()
            ->selectRaw('jenis_kelamin, count(*) as total')
            ->groupBy('jenis_kelamin')
            ->get()
            ->map(fn ($item) => [
                'jenis_kelamin' => $item->jenis_kelamin->value ?? $item->jenis_kelamin,
                'total' => $item->total,
            ]);

        // KGB segera: count pegawai KGB <= 60 hari (2 months)
        $kgbSegera = app(KgbMonitoringService::class)->getUpcomingKgb(2)->count();

        // KP eligible: count pegawai eligible KP
        $kpEligible = app(KenaikanPangkatMonitoringService::class)
            ->getUpcomingKenaikanPangkat()
            ->filter(fn ($kp) => $kp['status'] === 'Sudah Eligible')
            ->count();

        // Distribusi per jabatan (top 6)
        $distribusiJabatan = Pegawai::aktif()
            ->with('jabatan')
            ->get()
            ->groupBy('ref_jabatan_id')
            ->map(fn ($pegawaiGroup) => [
                'nama' => $pegawaiGroup->first()->jabatan?->nama ?? 'Tidak Ada Jabatan',
                'pegawai_count' => $pegawaiGroup->count(),
            ])
            ->sortByDesc('pegawai_count')
            ->take(6)
            ->values();

        // Distribusi per pendidikan terakhir
        $distribusiPendidikan = Pegawai::aktif()
            ->whereNotNull('pendidikan_terakhir')
            ->get()
            ->groupBy('pendidikan_terakhir')
            ->map(function ($pegawaiGroup, $pendidikan) {
                $label = JenjangPendidikan::tryFrom($pendidikan)?->label() ?? strtoupper($pendidikan);

                return [
                    'pendidikan' => $label,
                    'pegawai_count' => $pegawaiGroup->count(),
                ];
            })
            ->sortByDesc('pegawai_count')
            ->values();

        // Pegawai baru bulan ini
        $pegawaiBaruBulanIni = Pegawai::aktif()
            ->whereMonth('tanggal_masuk', now()->month)
            ->whereYear('tanggal_masuk', now()->year)
            ->count();

        return Inertia::render('dashboard', [
            'stats' => [
                'total_pegawai_aktif' => $totalAktif,
                'distribusi_golongan' => $distribusiGolongan,
                'distribusi_unit_kerja' => $distribusiUnitKerja,
                'distribusi_jenis_kelamin' => $distribusiJenisKelamin,
                'kgb_segera_count' => $kgbSegera,
                'kp_eligible_count' => $kpEligible,
                'distribusi_jabatan' => $distribusiJabatan,
                'distribusi_pendidikan' => $distribusiPendidikan,
                'pegawai_baru_bulan_ini' => $pegawaiBaruBulanIni,
            ],
        ]);
    }
}
