<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreRiwayatJabatanRequest;
use App\Http\Requests\Kepegawaian\UpdateRiwayatJabatanRequest;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatJabatanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
            new Middleware('role:admin,operator'),
        ];
    }

    public function index(Pegawai $pegawai): Response
    {
        $pegawai->load(['jabatan:id,nama,kode', 'unitKerja:id,nama,kode']);

        return Inertia::render('kepegawaian/pegawai/riwayat-jabatan', [
            'pegawai' => [
                'id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama_lengkap' => $pegawai->nama_lengkap,
                'jabatan' => $pegawai->jabatan?->only(['id', 'nama', 'kode']),
                'unit_kerja' => $pegawai->unitKerja?->only(['id', 'nama', 'kode']),
            ],
            'storeUrl' => route('kepegawaian.pegawai.riwayat-jabatan.store', $pegawai),
            'riwayatJabatan' => $pegawai->riwayatJabatan()
                ->with(['jabatan:id,nama,kode', 'unitKerja:id,nama,kode'])
                ->orderByDesc('is_aktif')
                ->orderByDesc('tmt')
                ->orderByDesc('tanggal_sk')
                ->get()
                ->map(fn (RiwayatJabatan $riwayatJabatan) => [
                    'id' => $riwayatJabatan->id,
                    'pegawai_id' => $riwayatJabatan->pegawai_id,
                    'ref_jabatan_id' => $riwayatJabatan->ref_jabatan_id,
                    'ref_unit_kerja_id' => $riwayatJabatan->ref_unit_kerja_id,
                    'no_sk' => $riwayatJabatan->no_sk,
                    'tanggal_sk' => $riwayatJabatan->tanggal_sk?->toDateString(),
                    'tmt' => $riwayatJabatan->tmt?->toDateString(),
                    'pejabat_penetap' => $riwayatJabatan->pejabat_penetap,
                    'is_aktif' => $riwayatJabatan->is_aktif,
                    'keterangan' => $riwayatJabatan->keterangan,
                    'jabatan' => $riwayatJabatan->jabatan?->only(['id', 'nama', 'kode']),
                    'unit_kerja' => $riwayatJabatan->unitKerja?->only(['id', 'nama', 'kode']),
                    'update_url' => route('kepegawaian.pegawai.riwayat-jabatan.update', [
                        'pegawai' => $pegawai,
                        'riwayat_jabatan' => $riwayatJabatan,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.riwayat-jabatan.destroy', [
                        'pegawai' => $pegawai,
                        'riwayat_jabatan' => $riwayatJabatan,
                    ]),
                ])
                ->values(),
            'referensi' => [
                'jabatan' => RefJabatan::query()
                    ->orderBy('nama')
                    ->get(['id', 'kode', 'nama']),
                'unit_kerja' => RefUnitKerja::query()
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(['id', 'kode', 'nama']),
            ],
        ]);
    }

    public function store(StoreRiwayatJabatanRequest $request, Pegawai $pegawai): RedirectResponse
    {
        DB::transaction(function () use ($request, $pegawai): void {
            $riwayatJabatan = $pegawai->riwayatJabatan()->create($request->validated());

            if ($riwayatJabatan->is_aktif) {
                $this->syncRiwayatAktif($riwayatJabatan, $pegawai);
            }
        });

        return to_route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai);
    }

    public function update(UpdateRiwayatJabatanRequest $request, Pegawai $pegawai, RiwayatJabatan $riwayatJabatan): RedirectResponse
    {
        abort_unless($riwayatJabatan->pegawai_id === $pegawai->id, 404);
        DB::transaction(function () use ($request, $riwayatJabatan, $pegawai): void {
            $riwayatJabatan->update($request->validated());

            if ($riwayatJabatan->is_aktif) {
                $this->syncRiwayatAktif($riwayatJabatan, $pegawai);
            }
        });

        return to_route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai);
    }

    public function destroy(Pegawai $pegawai, RiwayatJabatan $riwayatJabatan): RedirectResponse
    {
        abort_unless($riwayatJabatan->pegawai_id === $pegawai->id, 404);

        $riwayatJabatan->delete();

        return to_route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai);
    }

    private function syncRiwayatAktif(RiwayatJabatan $riwayatJabatan, Pegawai $pegawai): void
    {
        RiwayatJabatan::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('id', '!=', $riwayatJabatan->id)
            ->update(['is_aktif' => false]);

        $pegawai->update([
            'ref_jabatan_id' => $riwayatJabatan->ref_jabatan_id,
            'ref_unit_kerja_id' => $riwayatJabatan->ref_unit_kerja_id,
        ]);
    }
}
