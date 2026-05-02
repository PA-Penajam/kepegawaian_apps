<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Enums\JenjangPendidikan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreRiwayatPendidikanRequest;
use App\Http\Requests\Kepegawaian\UpdateRiwayatPendidikanRequest;
use App\Models\Pegawai;
use App\Models\RiwayatPendidikan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatPendidikanController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        Gate::authorize('view', $pegawai);

        $pegawai->load([
            'riwayatPendidikan' => fn ($query) => $query
                ->orderByDesc('tahun_lulus')
                ->orderByDesc('tanggal_ijazah')
                ->orderByDesc('created_at'),
        ]);

        return Inertia::render('kepegawaian/pegawai/riwayat-pendidikan', [
            'pegawai' => $pegawai->only(['id', 'nip', 'nama_lengkap', 'pendidikan_terakhir']),
            'storeUrl' => route('kepegawaian.pegawai.riwayat-pendidikan.store', $pegawai),
            'riwayatPendidikan' => $pegawai->riwayatPendidikan
                ->map(fn (RiwayatPendidikan $riwayatPendidikan) => [
                    'id' => $riwayatPendidikan->id,
                    'jenjang' => $riwayatPendidikan->jenjang->value,
                    'jenjang_label' => $riwayatPendidikan->jenjang->label(),
                    'nama_sekolah' => $riwayatPendidikan->nama_sekolah,
                    'jurusan' => $riwayatPendidikan->jurusan,
                    'tahun_lulus' => $riwayatPendidikan->tahun_lulus,
                    'no_ijazah' => $riwayatPendidikan->no_ijazah,
                    'tanggal_ijazah' => $riwayatPendidikan->tanggal_ijazah?->format('Y-m-d'),
                    'keterangan' => $riwayatPendidikan->keterangan,
                    'update_url' => route('kepegawaian.pegawai.riwayat-pendidikan.update', [
                        'pegawai' => $pegawai,
                        'riwayat_pendidikan' => $riwayatPendidikan,
                    ]),
                    'delete_url' => route('kepegawaian.pegawai.riwayat-pendidikan.destroy', [
                        'pegawai' => $pegawai,
                        'riwayat_pendidikan' => $riwayatPendidikan,
                    ]),
                ])
                ->values(),
            'jenjangOptions' => collect(JenjangPendidikan::cases())
                ->map(fn (JenjangPendidikan $jenjang) => [
                    'value' => $jenjang->value,
                    'label' => $jenjang->label(),
                ])
                ->values(),
        ]);
    }

    public function store(StoreRiwayatPendidikanRequest $request, Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        try {
            $pegawai->riwayatPendidikan()->create($request->validated());

            return to_route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai)
                ->with('success', 'Riwayat pendidikan berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menambahkan riwayat pendidikan. Silakan coba lagi.');
        }
    }

    public function update(UpdateRiwayatPendidikanRequest $request, Pegawai $pegawai, RiwayatPendidikan $riwayatPendidikan): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        abort_unless($riwayatPendidikan->pegawai_id === $pegawai->id, 404);

        try {
            $riwayatPendidikan->update($request->validated());

            return to_route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai)
                ->with('success', 'Riwayat pendidikan berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat memperbarui riwayat pendidikan. Silakan coba lagi.');
        }
    }

    public function destroy(Pegawai $pegawai, RiwayatPendidikan $riwayatPendidikan): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        abort_unless($riwayatPendidikan->pegawai_id === $pegawai->id, 404);

        try {
            $riwayatPendidikan->delete();

            return to_route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai)
                ->with('success', 'Riwayat pendidikan berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus riwayat pendidikan. Silakan coba lagi.');
        }
    }
}
