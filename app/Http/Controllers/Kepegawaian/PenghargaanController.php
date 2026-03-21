<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StorePenghargaanRequest;
use App\Http\Requests\Kepegawaian\UpdatePenghargaanRequest;
use App\Models\Pegawai;
use App\Models\Penghargaan;
use App\Models\RefJenisPenghargaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PenghargaanController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        Gate::authorize('view', $pegawai);

        return Inertia::render('kepegawaian/pegawai/penghargaan', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
            ],
            'storeUrl' => route('kepegawaian.pegawai.penghargaan.store', $pegawai),
            'penghargaan' => $pegawai->penghargaan()
                ->orderByDesc('tanggal_sk')
                ->orderBy('nama_penghargaan')
                ->get()
                ->map(fn (Penghargaan $penghargaan): array => [
                    'id' => $penghargaan->id,
                    'ref_jenis_penghargaan_id' => $penghargaan->ref_jenis_penghargaan_id,
                    'nama_penghargaan' => $penghargaan->nama_penghargaan,
                    'no_sk' => $penghargaan->no_sk,
                    'tanggal_sk' => $penghargaan->tanggal_sk?->toDateString(),
                    'pejabat_penetap' => $penghargaan->pejabat_penetap,
                    'keterangan' => $penghargaan->keterangan,
                    'update_url' => route('kepegawaian.pegawai.penghargaan.update', [
                        'pegawai' => $pegawai,
                        'penghargaan' => $penghargaan,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.penghargaan.destroy', [
                        'pegawai' => $pegawai,
                        'penghargaan' => $penghargaan,
                    ]),
                ])
                ->values(),
            'jenisPenghargaanOptions' => RefJenisPenghargaan::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(StorePenghargaanRequest $request, Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $pegawai->penghargaan()->create($request->validated());

        return to_route('kepegawaian.pegawai.penghargaan.index', $pegawai);
    }

    public function update(UpdatePenghargaanRequest $request, Pegawai $pegawai, Penghargaan $penghargaan): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $this->ensureBelongsToPegawai($pegawai, $penghargaan);

        $penghargaan->update($request->validated());

        return to_route('kepegawaian.pegawai.penghargaan.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, Penghargaan $penghargaan): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $this->ensureBelongsToPegawai($pegawai, $penghargaan);

        $penghargaan->delete();

        return to_route('kepegawaian.pegawai.penghargaan.index', $pegawai);
    }

    private function ensureBelongsToPegawai(Pegawai $pegawai, Penghargaan $penghargaan): void
    {
        abort_unless($penghargaan->pegawai_id === $pegawai->id, 404);
    }
}
