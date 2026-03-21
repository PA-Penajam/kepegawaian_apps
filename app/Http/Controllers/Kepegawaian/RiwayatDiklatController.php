<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreRiwayatDiklatRequest;
use App\Http\Requests\Kepegawaian\UpdateRiwayatDiklatRequest;
use App\Models\Pegawai;
use App\Models\RefJenisDiklat;
use App\Models\RiwayatDiklat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatDiklatController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        Gate::authorize('view', $pegawai);

        return Inertia::render('kepegawaian/pegawai/riwayat-diklat', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nama_lengkap' => $pegawai->nama_lengkap,
            ],
            'storeUrl' => route('kepegawaian.pegawai.riwayat-diklat.store', $pegawai),
            'riwayatDiklat' => $pegawai->riwayatDiklat()
                ->with('jenisDiklat:id,nama')
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (RiwayatDiklat $riwayatDiklat) => [
                    'id' => $riwayatDiklat->id,
                    'ref_jenis_diklat_id' => $riwayatDiklat->ref_jenis_diklat_id,
                    'jenis_diklat' => $riwayatDiklat->jenisDiklat?->nama,
                    'nama_diklat' => $riwayatDiklat->nama_diklat,
                    'penyelenggara' => $riwayatDiklat->penyelenggara,
                    'tempat' => $riwayatDiklat->tempat,
                    'tanggal_mulai' => $riwayatDiklat->tanggal_mulai?->format('Y-m-d'),
                    'tanggal_selesai' => $riwayatDiklat->tanggal_selesai?->format('Y-m-d'),
                    'jam_pelajaran' => $riwayatDiklat->jam_pelajaran,
                    'no_sertifikat' => $riwayatDiklat->no_sertifikat,
                    'tanggal_sertifikat' => $riwayatDiklat->tanggal_sertifikat?->format('Y-m-d'),
                    'keterangan' => $riwayatDiklat->keterangan,
                    'update_url' => route('kepegawaian.pegawai.riwayat-diklat.update', [
                        'pegawai' => $pegawai,
                        'riwayatDiklat' => $riwayatDiklat,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.riwayat-diklat.destroy', [
                        'pegawai' => $pegawai,
                        'riwayatDiklat' => $riwayatDiklat,
                    ]),
                ])
                ->values()
                ->all(),
            'jenisDiklatOptions' => RefJenisDiklat::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(StoreRiwayatDiklatRequest $request, Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $pegawai->riwayatDiklat()->create($request->validated());

        return redirect()->route('kepegawaian.pegawai.riwayat-diklat.index', $pegawai);
    }

    public function update(UpdateRiwayatDiklatRequest $request, Pegawai $pegawai, RiwayatDiklat $riwayatDiklat): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        abort_unless($riwayatDiklat->pegawai_id === $pegawai->id, 404);

        $riwayatDiklat->update($request->validated());

        return redirect()->route('kepegawaian.pegawai.riwayat-diklat.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, RiwayatDiklat $riwayatDiklat): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        abort_unless($riwayatDiklat->pegawai_id === $pegawai->id, 404);

        $riwayatDiklat->delete();

        return redirect()->route('kepegawaian.pegawai.riwayat-diklat.index', $pegawai);
    }
}
