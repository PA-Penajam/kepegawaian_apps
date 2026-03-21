<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreKeluargaRequest;
use App\Http\Requests\Kepegawaian\UpdateKeluargaRequest;
use App\Models\Keluarga;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KeluargaController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        return Inertia::render('kepegawaian/pegawai/keluarga', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama_lengkap' => $pegawai->nama_lengkap,
            ],
            'storeUrl' => route('kepegawaian.pegawai.keluarga.store', $pegawai),
            'keluarga' => $pegawai->keluarga()
                ->orderBy('hubungan')
                ->orderBy('nama')
                ->get()
                ->map(fn (Keluarga $keluarga) => [
                    'id' => $keluarga->id,
                    'hubungan' => $keluarga->getRawOriginal('hubungan'),
                    'hubungan_label' => $keluarga->hubungan?->label(),
                    'nama' => $keluarga->nama,
                    'tempat_lahir' => $keluarga->tempat_lahir,
                    'tanggal_lahir' => $keluarga->tanggal_lahir?->toDateString(),
                    'jenis_kelamin' => $keluarga->getRawOriginal('jenis_kelamin'),
                    'pekerjaan' => $keluarga->pekerjaan,
                    'pendidikan' => $keluarga->pendidikan,
                    'keterangan' => $keluarga->keterangan,
                    'update_url' => route('kepegawaian.pegawai.keluarga.update', [
                        'pegawai' => $pegawai,
                        'keluarga' => $keluarga,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.keluarga.destroy', [
                        'pegawai' => $pegawai,
                        'keluarga' => $keluarga,
                    ]),
                ])
                ->values(),
        ]);
    }

    public function store(StoreKeluargaRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $pegawai->keluarga()->create($request->validated());

        return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
    }

    public function update(UpdateKeluargaRequest $request, Pegawai $pegawai, Keluarga $keluarga): RedirectResponse
    {
        $this->ensureBelongsToPegawai($pegawai, $keluarga);

        $keluarga->update($request->validated());

        return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, Keluarga $keluarga): RedirectResponse
    {
        $this->ensureBelongsToPegawai($pegawai, $keluarga);

        $keluarga->delete();

        return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
    }

    private function ensureBelongsToPegawai(Pegawai $pegawai, Keluarga $keluarga): void
    {
        abort_unless($keluarga->pegawai_id === $pegawai->id, 404);
    }
}
