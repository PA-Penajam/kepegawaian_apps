<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Serialisasikan manual dokumenPegawai tanpa relasi agar key snake_case
        // 'jenis_dokumen' dari relasi jenisDokumen tidak menimpa atribut string.
        $pegawaiData = $pegawai->toArray();
        $pegawaiData['dokumen_pegawai'] = $pegawai->dokumenPegawai
            ->map(function (DokumenPegawai $dokumen): array {
                $data = $dokumen->withoutRelations()->toArray();
                $namaJenis = $dokumen->jenisDokumen?->nama ?? $dokumen->jenis_dokumen;
                $data['jenis_dokumen'] = filled($namaJenis) ? (string) $namaJenis : '-';

                return $data;
            })
            ->values()
            ->all();

        return Inertia::render('self-service/detail', [
            'pegawai' => $pegawaiData,
        ]);
    }

    /**
     * Menampilkan berkas foto profil milik pegawai yang sedang login.
     */
    public function showFoto(Request $request): StreamedResponse
    {
        $pegawai = $request->user();

        if (! $pegawai instanceof Pegawai || blank($pegawai->foto) || ! Storage::disk('local')->exists($pegawai->foto)) {
            abort(404, 'Foto pegawai tidak ditemukan.');
        }

        return Storage::disk('local')->response($pegawai->foto);
    }

    private function currentPegawai(Request $request, array $relations): Pegawai
    {
        // $request->user() sudah return Pegawai (Pegawai = User)
        return $request->user()->load($relations);
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
            'riwayatPangkat.pangkat',
            'riwayatJabatan.jabatan',
            'riwayatJabatan.unitKerja',
            'riwayatPendidikan',
            'riwayatDiklat.jenisDiklat',
            'keluarga',
            'penghargaan.jenisPenghargaan',
            'hukumanDisiplin.jenisHukumanDisiplin',
            'dokumenPegawai.jenisDokumen',
        ];
    }

    private function resolveKgbInfo(Pegawai $pegawai): ?array
    {
        $riwayatAktif = $pegawai->riwayatPangkat->firstWhere('is_aktif', true);
        if ($riwayatAktif === null || $riwayatAktif->tmt === null) {
            return null;
        }

        try {
            $status = $this->kgbService->getKgbStatus($pegawai);

            return [
                'tanggal_kgb_berikutnya' => $status['tanggal_kgb_berikutnya']->toDateString(),
                'sisa_hari' => $status['sisa_hari'],
                'status' => $status['status'],
            ];
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function resolveKpInfo(Pegawai $pegawai): ?array
    {
        $riwayatAktif = $pegawai->riwayatPangkat->firstWhere('is_aktif', true);
        if ($riwayatAktif === null || $riwayatAktif->tmt === null) {
            return null;
        }

        try {
            $status = $this->kpService->getKpStatus($pegawai);

            return [
                'tmt_kp_berikutnya' => $status['tmt_kp_berikutnya']->toDateString(),
                'periode_usul' => $status['periode_usul'],
                'batas_usul' => $status['batas_usul']->toDateString(),
                'sisa_hari_usul' => $status['sisa_hari_usul'],
                'status' => $status['status'],
            ];
        } catch (\RuntimeException) {
            return null;
        }
    }
}
