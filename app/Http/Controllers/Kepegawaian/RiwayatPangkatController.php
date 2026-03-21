<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreRiwayatPangkatRequest;
use App\Http\Requests\Kepegawaian\UpdateRiwayatPangkatRequest;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Services\RiwayatPangkatService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatPangkatController extends Controller
{
    public function __construct(private readonly RiwayatPangkatService $riwayatPangkatService) {}

    public function index(Pegawai $pegawai): Response
    {
        $pegawai->load('pangkat');

        return Inertia::render('kepegawaian/pegawai/riwayat-pangkat', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama_lengkap' => $pegawai->nama_lengkap,
                'ref_pangkat_id' => $pegawai->ref_pangkat_id,
                'pangkat' => $pegawai->pangkat === null ? null : [
                    'id' => $pegawai->pangkat->id,
                    'kode' => $pegawai->pangkat->kode,
                    'nama' => $pegawai->pangkat->nama,
                    'label' => $pegawai->nama_pangkat_lengkap,
                ],
            ],
            'storeUrl' => route('kepegawaian.pegawai.riwayat-pangkat.store', $pegawai),
            'riwayatPangkat' => $pegawai->riwayatPangkat()
                ->with('pangkat')
                ->orderByDesc('is_aktif')
                ->orderByDesc('tmt')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (RiwayatPangkat $riwayatPangkat): array => [
                    'id' => $riwayatPangkat->id,
                    'ref_pangkat_id' => $riwayatPangkat->ref_pangkat_id,
                    'no_sk' => $riwayatPangkat->no_sk,
                    'tanggal_sk' => $riwayatPangkat->tanggal_sk?->toDateString(),
                    'tmt' => $riwayatPangkat->tmt?->toDateString(),
                    'pejabat_penetap' => $riwayatPangkat->pejabat_penetap,
                    'masa_kerja_tahun' => $riwayatPangkat->masa_kerja_tahun,
                    'masa_kerja_bulan' => $riwayatPangkat->masa_kerja_bulan,
                    'gaji_pokok' => $riwayatPangkat->gaji_pokok,
                    'is_aktif' => $riwayatPangkat->is_aktif,
                    'keterangan' => $riwayatPangkat->keterangan,
                    'pangkat' => $riwayatPangkat->pangkat === null ? null : [
                        'id' => $riwayatPangkat->pangkat->id,
                        'kode' => $riwayatPangkat->pangkat->kode,
                        'nama' => $riwayatPangkat->pangkat->nama,
                        'label' => sprintf('%s - %s', $riwayatPangkat->pangkat->nama, $riwayatPangkat->pangkat->kode),
                    ],
                    'update_url' => route('kepegawaian.pegawai.riwayat-pangkat.update', [
                        'pegawai' => $pegawai,
                        'riwayat_pangkat' => $riwayatPangkat,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.riwayat-pangkat.destroy', [
                        'pegawai' => $pegawai,
                        'riwayat_pangkat' => $riwayatPangkat,
                    ]),
                ])
                ->values(),
            'refPangkatOptions' => RefPangkat::query()
                ->orderBy('urutan')
                ->get()
                ->map(fn (RefPangkat $refPangkat): array => [
                    'id' => $refPangkat->id,
                    'kode' => $refPangkat->kode,
                    'nama' => $refPangkat->nama,
                    'label' => sprintf('%s - %s', $refPangkat->nama, $refPangkat->kode),
                ])
                ->values(),
        ]);
    }

    public function store(StoreRiwayatPangkatRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $this->riwayatPangkatService->store($pegawai, $request->validated());

        return to_route('kepegawaian.pegawai.riwayat-pangkat.index', $pegawai);
    }

    public function update(UpdateRiwayatPangkatRequest $request, Pegawai $pegawai, RiwayatPangkat $riwayatPangkat): RedirectResponse
    {
        abort_unless($riwayatPangkat->pegawai_id === $pegawai->id, 404);

        $this->riwayatPangkatService->update($riwayatPangkat, $pegawai, $request->validated());

        return to_route('kepegawaian.pegawai.riwayat-pangkat.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, RiwayatPangkat $riwayatPangkat): RedirectResponse
    {
        abort_unless($riwayatPangkat->pegawai_id === $pegawai->id, 404);

        $riwayatPangkat->delete();

        return to_route('kepegawaian.pegawai.riwayat-pangkat.index', $pegawai);
    }
}
