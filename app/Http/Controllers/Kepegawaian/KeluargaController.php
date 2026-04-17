<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreKeluargaRequest;
use App\Http\Requests\Kepegawaian\UpdateKeluargaRequest;
use App\Models\Keluarga;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KeluargaController extends Controller
{
    public function __construct(
        private \App\Services\PengajuanPerubahanData\SubmitPengajuanPerubahanDataService $submitPengajuanPerubahanData
    ) {
    }
    public function index(Pegawai $pegawai): Response
    {
        Gate::authorize('view', $pegawai);

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
        Gate::authorize('update', $pegawai);

        // Intersepsi operator: jadikan pending request alih-alih store langsung
        if ($request->user()?->isOperator()) {
            $hubungan = $request->validated('hubungan');
            $domain = match ($hubungan) {
                'Suami', 'Istri' => 'pasangan',
                'Anak' => 'anak',
                'Ayah', 'IbuKandung', 'IbuTiri', 'AyahMertua', 'IbuMertua' => 'orang_tua',
                default => 'keluarga_lain',
            };

            $this->submitPengajuanPerubahanData->handle(
                $request->user(),
                [
                    'domain' => $domain,
                    'aksi' => 'create',
                    'target_type' => 'keluarga',
                    'target_id' => null,
                    'subject_pegawai_id' => $pegawai->id,
                    'after_payload' => $request->validated(),
                    'lampiran' => [],
                ],
                'operator',
            );

            return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
        }

        $pegawai->keluarga()->create($request->validated());

        return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
    }

    public function update(UpdateKeluargaRequest $request, Pegawai $pegawai, Keluarga $keluarga): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $this->ensureBelongsToPegawai($pegawai, $keluarga);

        // Intersepsi operator: jadikan pending request alih-alih update langsung
        if ($request->user()?->isOperator()) {
            $hubungan = $request->validated('hubungan') ?? $keluarga->getRawOriginal('hubungan');
            $domain = match ($hubungan) {
                'Suami', 'Istri' => 'pasangan',
                'Anak' => 'anak',
                'Ayah', 'IbuKandung', 'IbuTiri', 'AyahMertua', 'IbuMertua' => 'orang_tua',
                default => 'keluarga_lain',
            };

            $this->submitPengajuanPerubahanData->handle(
                $request->user(),
                [
                    'domain' => $domain,
                    'aksi' => 'update',
                    'target_type' => 'keluarga',
                    'target_id' => $keluarga->id,
                    'subject_pegawai_id' => $pegawai->id,
                    'after_payload' => $request->safe()->except(['_token', '_method']),
                    'lampiran' => [],
                ],
                'operator',
            );

            return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
        }

        $keluarga->update($request->validated());

        return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, Keluarga $keluarga): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $this->ensureBelongsToPegawai($pegawai, $keluarga);

        // Intersepsi operator: jadikan pending request alih-alih delete langsung
        if (request()->user()?->isOperator()) {
            $hubungan = $keluarga->getRawOriginal('hubungan');
            $domain = match ($hubungan) {
                'Suami', 'Istri' => 'pasangan',
                'Anak' => 'anak',
                'Ayah', 'IbuKandung', 'IbuTiri', 'AyahMertua', 'IbuMertua' => 'orang_tua',
                default => 'keluarga_lain',
            };

            $this->submitPengajuanPerubahanData->handle(
                request()->user(),
                [
                    'domain' => $domain,
                    'aksi' => 'delete',
                    'target_type' => 'keluarga',
                    'target_id' => $keluarga->id,
                    'subject_pegawai_id' => $pegawai->id,
                    'after_payload' => [],
                    'lampiran' => [],
                ],
                'operator',
            );

            return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
        }

        $keluarga->delete();

        return to_route('kepegawaian.pegawai.keluarga.index', $pegawai);
    }

    private function ensureBelongsToPegawai(Pegawai $pegawai, Keluarga $keluarga): void
    {
        abort_unless($keluarga->pegawai_id === $pegawai->id, 404);
    }
}
