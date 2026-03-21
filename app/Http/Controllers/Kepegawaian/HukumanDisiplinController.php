<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreHukumanDisiplinRequest;
use App\Http\Requests\Kepegawaian\UpdateHukumanDisiplinRequest;
use App\Models\HukumanDisiplin;
use App\Models\Pegawai;
use App\Models\RefJenisHukumanDisiplin;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HukumanDisiplinController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        return Inertia::render('kepegawaian/pegawai/hukuman-disiplin', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
            ],
            'storeUrl' => route('kepegawaian.pegawai.hukuman-disiplin.store', $pegawai),
            'hukumanDisiplin' => $pegawai->hukumanDisiplin()
                ->orderByDesc('tmt_berlaku')
                ->get()
                ->map(fn (HukumanDisiplin $hukumanDisiplin): array => [
                    'id' => $hukumanDisiplin->id,
                    'ref_jenis_hukuman_disiplin_id' => $hukumanDisiplin->ref_jenis_hukuman_disiplin_id,
                    'no_sk' => $hukumanDisiplin->no_sk,
                    'tanggal_sk' => $hukumanDisiplin->tanggal_sk?->toDateString(),
                    'tmt_berlaku' => $hukumanDisiplin->tmt_berlaku?->toDateString(),
                    'tmt_selesai' => $hukumanDisiplin->tmt_selesai?->toDateString(),
                    'pelanggaran' => $hukumanDisiplin->pelanggaran,
                    'pejabat_penetap' => $hukumanDisiplin->pejabat_penetap,
                    'keterangan' => $hukumanDisiplin->keterangan,
                    'update_url' => route('kepegawaian.pegawai.hukuman-disiplin.update', [
                        'pegawai' => $pegawai,
                        'hukuman_disiplin' => $hukumanDisiplin,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.hukuman-disiplin.destroy', [
                        'pegawai' => $pegawai,
                        'hukuman_disiplin' => $hukumanDisiplin,
                    ]),
                ])
                ->values(),
            'jenisHukumanOptions' => RefJenisHukumanDisiplin::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(StoreHukumanDisiplinRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $pegawai->hukumanDisiplin()->create($request->validated());

        return to_route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai);
    }

    public function update(UpdateHukumanDisiplinRequest $request, Pegawai $pegawai, HukumanDisiplin $hukumanDisiplin): RedirectResponse
    {
        $this->ensureBelongsToPegawai($pegawai, $hukumanDisiplin);

        $hukumanDisiplin->update($request->validated());

        return to_route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, HukumanDisiplin $hukumanDisiplin): RedirectResponse
    {
        $this->ensureBelongsToPegawai($pegawai, $hukumanDisiplin);

        $hukumanDisiplin->delete();

        return to_route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai);
    }

    private function ensureBelongsToPegawai(Pegawai $pegawai, HukumanDisiplin $hukumanDisiplin): void
    {
        abort_unless($hukumanDisiplin->pegawai_id === $pegawai->id, 404);
    }
}
