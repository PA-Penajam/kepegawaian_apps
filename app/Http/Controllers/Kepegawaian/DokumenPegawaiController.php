<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreDokumenPegawaiRequest;
use App\Http\Requests\Kepegawaian\UpdateDokumenPegawaiRequest;
use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DokumenPegawaiController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        $pegawai->load([
            'dokumenPegawai' => fn ($query) => $query
                ->orderBy('jenis_dokumen')
                ->orderByDesc('tanggal_dokumen')
                ->orderByDesc('created_at'),
        ]);

        return Inertia::render('kepegawaian/pegawai/dokumen-pegawai', [
            'pegawai' => $pegawai->only(['id', 'nip', 'nama_lengkap']),
            'storeUrl' => route('kepegawaian.pegawai.dokumen.store', $pegawai),
            'dokumen' => $pegawai->dokumenPegawai
                ->map(fn (DokumenPegawai $dokumenPegawai) => [
                    'id' => $dokumenPegawai->id,
                    'pegawai_id' => $dokumenPegawai->pegawai_id,
                    'jenis_dokumen' => $dokumenPegawai->jenis_dokumen,
                    'nomor_dokumen' => $dokumenPegawai->nomor_dokumen,
                    'tanggal_dokumen' => $dokumenPegawai->tanggal_dokumen?->toDateString(),
                    'file_path' => $dokumenPegawai->file_path,
                    'keterangan' => $dokumenPegawai->keterangan,
                    'update_url' => route('kepegawaian.pegawai.dokumen.update', [
                        'pegawai' => $pegawai,
                        'dokumen' => $dokumenPegawai,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.dokumen.destroy', [
                        'pegawai' => $pegawai,
                        'dokumen' => $dokumenPegawai,
                    ]),
                ])
                ->values(),
        ]);
    }

    public function store(StoreDokumenPegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $pegawai->dokumenPegawai()->create($request->validated());

        return to_route('kepegawaian.pegawai.dokumen.index', $pegawai);
    }

    public function update(
        UpdateDokumenPegawaiRequest $request,
        Pegawai $pegawai,
        DokumenPegawai $dokumen,
    ): RedirectResponse {
        $this->ensureDokumenMilikPegawai($pegawai, $dokumen);

        $dokumen->update($request->validated());

        return to_route('kepegawaian.pegawai.dokumen.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, DokumenPegawai $dokumen): RedirectResponse
    {
        $this->ensureDokumenMilikPegawai($pegawai, $dokumen);

        $dokumen->delete();

        return to_route('kepegawaian.pegawai.dokumen.index', $pegawai);
    }

    private function ensureDokumenMilikPegawai(Pegawai $pegawai, DokumenPegawai $dokumen): void
    {
        abort_unless($dokumen->pegawai_id === $pegawai->id, 404);
    }
}
