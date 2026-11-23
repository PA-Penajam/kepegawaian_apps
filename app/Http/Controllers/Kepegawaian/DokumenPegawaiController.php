<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StoreDokumenPegawaiRequest;
use App\Http\Requests\Kepegawaian\UpdateDokumenPegawaiRequest;
use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use App\Models\RefJenisDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenPegawaiController extends Controller
{
    public function index(Pegawai $pegawai): Response
    {
        Gate::authorize('view', $pegawai);

        $pegawai->load([
            'dokumenPegawai' => fn ($query) => $query
                ->with('jenisDokumen')
                ->orderBy('jenis_dokumen')
                ->orderByDesc('tanggal_dokumen')
                ->orderByDesc('created_at'),
        ]);

        return Inertia::render('kepegawaian/pegawai/dokumen-pegawai', [
            'pegawai' => $pegawai->only(['id', 'nip', 'nama_lengkap']),
            'storeUrl' => route('kepegawaian.pegawai.dokumen.store', $pegawai),
            'refJenisDokumen' => RefJenisDokumen::query()
                ->orderBy('nama')
                ->get(['id', 'nama']),
            'dokumen' => $pegawai->dokumenPegawai
                ->map(function (DokumenPegawai $dokumenPegawai) use ($pegawai): array {
                    $isSafeFile = filled($dokumenPegawai->file_path) && ! $this->isUnsafeUri($dokumenPegawai->file_path);

                    return [
                        'id' => $dokumenPegawai->id,
                        'pegawai_id' => $dokumenPegawai->pegawai_id,
                        'ref_jenis_dokumen_id' => $dokumenPegawai->ref_jenis_dokumen_id,
                        'jenis_dokumen' => $dokumenPegawai->jenisDokumen?->nama ?? $dokumenPegawai->jenis_dokumen,
                        'nomor_dokumen' => $dokumenPegawai->nomor_dokumen,
                        'tanggal_dokumen' => $dokumenPegawai->tanggal_dokumen?->toDateString(),
                        'file_path' => $isSafeFile ? $dokumenPegawai->file_path : null,
                        'has_file' => $isSafeFile,
                        'download_url' => $isSafeFile
                            ? route('kepegawaian.pegawai.dokumen.download', [
                                'pegawai' => $pegawai,
                                'dokumen' => $dokumenPegawai,
                            ], false)
                            : null,
                        'keterangan' => $dokumenPegawai->keterangan,
                        'update_url' => route('kepegawaian.pegawai.dokumen.update', [
                            'pegawai' => $pegawai,
                            'dokumen' => $dokumenPegawai,
                        ]),
                        'delete_url' => route('kepegawaian.pegawai.dokumen.destroy', [
                            'pegawai' => $pegawai,
                            'dokumen' => $dokumenPegawai,
                        ]),
                    ];
                })
                ->values(),
        ]);
    }

    public function store(StoreDokumenPegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $validated = $request->safe()->except(['file']);
        $resolvedJenis = $this->resolveJenisDokumen(
            $validated['ref_jenis_dokumen_id'] ?? null,
            $validated['jenis_dokumen'] ?? null,
        );

        $uploadedPath = Storage::disk('google')->putFile("dokumen-pegawai/{$pegawai->id}", $request->file('file'));
        if ($uploadedPath === false) {
            return back()->with('error', 'Gagal mengunggah berkas dokumen ke penyimpanan Google Drive.');
        }

        try {
            DB::transaction(function () use ($pegawai, $validated, $resolvedJenis, $uploadedPath): void {
                $dokumen = new DokumenPegawai([
                    'ref_jenis_dokumen_id' => $resolvedJenis['ref_jenis_dokumen_id'],
                    'jenis_dokumen' => $resolvedJenis['jenis_dokumen'],
                    'nomor_dokumen' => $validated['nomor_dokumen'] ?? null,
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? null,
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);

                $dokumen->forceFill([
                    'pegawai_id' => $pegawai->id,
                    'file_path' => $uploadedPath,
                ])->save();
            });
        } catch (\Throwable $e) {
            report($e);
            try {
                if (Storage::disk('google')->exists($uploadedPath)) {
                    Storage::disk('google')->delete($uploadedPath);
                }
            } catch (FilesystemException $cleanupException) {
                report($cleanupException);
            }

            throw $e;
        }

        return to_route('kepegawaian.pegawai.dokumen.index', $pegawai)
            ->with('success', 'Dokumen pegawai berhasil diunggah.');
    }

    public function update(
        UpdateDokumenPegawaiRequest $request,
        Pegawai $pegawai,
        DokumenPegawai $dokumen,
    ): RedirectResponse {
        Gate::authorize('update', $pegawai);

        $this->ensureDokumenMilikPegawai($pegawai, $dokumen);

        $validated = $request->safe()->except(['file']);
        $resolvedJenis = $this->resolveJenisDokumen(
            $validated['ref_jenis_dokumen_id'] ?? null,
            $validated['jenis_dokumen'] ?? null,
        );

        $oldPath = $dokumen->file_path;
        $newPath = null;

        if ($request->hasFile('file')) {
            $storedPath = Storage::disk('google')->putFile("dokumen-pegawai/{$pegawai->id}", $request->file('file'));
            if ($storedPath === false) {
                return back()->with('error', 'Gagal mengunggah berkas dokumen baru ke penyimpanan Google Drive.');
            }
            $newPath = $storedPath;
        }

        try {
            DB::transaction(function () use ($dokumen, $validated, $resolvedJenis, $newPath): void {
                $dokumen->fill([
                    'ref_jenis_dokumen_id' => $resolvedJenis['ref_jenis_dokumen_id'],
                    'jenis_dokumen' => $resolvedJenis['jenis_dokumen'],
                    'nomor_dokumen' => $validated['nomor_dokumen'] ?? null,
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? null,
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);

                if ($newPath !== null) {
                    $dokumen->forceFill(['file_path' => $newPath]);
                }

                $dokumen->save();
            });
        } catch (\Throwable $e) {
            report($e);
            if ($newPath !== null) {
                try {
                    if (Storage::disk('google')->exists($newPath)) {
                        Storage::disk('google')->delete($newPath);
                    }
                } catch (FilesystemException $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $e;
        }

        if (
            $newPath !== null
            && filled($oldPath)
            && ! $this->isUnsafeUri($oldPath)
            && $oldPath !== $newPath
        ) {
            try {
                if (Storage::disk('google')->exists($oldPath)) {
                    Storage::disk('google')->delete($oldPath);
                }
            } catch (FilesystemException $e) {
                report($e);
            }
        }

        return to_route('kepegawaian.pegawai.dokumen.index', $pegawai)
            ->with('success', 'Dokumen pegawai berhasil diperbarui.');
    }

    public function destroy(Pegawai $pegawai, DokumenPegawai $dokumen): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        $this->ensureDokumenMilikPegawai($pegawai, $dokumen);

        if (
            filled($dokumen->file_path)
            && ! $this->isUnsafeUri($dokumen->file_path)
        ) {
            try {
                if (Storage::disk('google')->exists($dokumen->file_path)) {
                    Storage::disk('google')->delete($dokumen->file_path);
                }
            } catch (FilesystemException $e) {
                report($e);
            }
        }

        $dokumen->delete();

        return to_route('kepegawaian.pegawai.dokumen.index', $pegawai)
            ->with('success', 'Dokumen pegawai berhasil dihapus.');
    }

    public function download(Pegawai $pegawai, DokumenPegawai $dokumen): StreamedResponse
    {
        Gate::authorize('view', $pegawai);

        $this->ensureDokumenMilikPegawai($pegawai, $dokumen);

        if (blank($dokumen->file_path) || $this->isUnsafeUri($dokumen->file_path)) {
            abort(404, 'Berkas dokumen tidak ditemukan.');
        }

        try {
            $disk = Storage::disk('google');

            if (! $disk->exists($dokumen->file_path)) {
                abort(404, 'Berkas dokumen tidak ditemukan.');
            }

            $extension = pathinfo($dokumen->file_path, PATHINFO_EXTENSION);
            $baseName = Str::slug((string) ($dokumen->jenis_dokumen ?: 'dokumen-pegawai'));
            $downloadName = $extension !== '' ? "{$baseName}-{$dokumen->id}.{$extension}" : "{$baseName}-{$dokumen->id}";

            return $disk->download($dokumen->file_path, $downloadName);
        } catch (FilesystemException) {
            abort(404, 'Berkas dokumen tidak ditemukan.');
        }
    }

    private function ensureDokumenMilikPegawai(Pegawai $pegawai, DokumenPegawai $dokumen): void
    {
        abort_unless($dokumen->pegawai_id === $pegawai->id, 404);
    }

    /**
     * Memvalidasi agar path berkas tidak mengandung skema URI berbahaya, URL eksternal atau
     * protokol-relatif, karakter kontrol/spasi Unicode, null byte, backslash, maupun traversal.
     */
    private function isUnsafeUri(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return true;
        }

        $trimmed = trim($path);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/\\')) {
            return true;
        }

        $normalized = preg_replace('/[\p{C}\p{Z}\s]+/u', '', $path);
        if ($normalized === null || $normalized === '') {
            return true;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $normalized) === 1) {
            return true;
        }

        if (str_starts_with($normalized, '//') || str_starts_with($normalized, '/\\')) {
            return true;
        }

        if (
            preg_match('#(?:^|/|\\\\)\.\.(?:$|/|\\\\)#', $path) === 1
            || preg_match('#(?:^|/|\\\\)\.\.(?:$|/|\\\\)#', $normalized) === 1
        ) {
            return true;
        }

        return false;
    }

    /**
     * Menyelaraskan ref_jenis_dokumen_id dan nama jenis_dokumen untuk kompatibilitas mundur.
     *
     * @return array{ref_jenis_dokumen_id: ?string, jenis_dokumen: string}
     */
    private function resolveJenisDokumen(?string $refJenisDokumenId, ?string $jenisDokumen): array
    {
        if (filled($refJenisDokumenId)) {
            $referensi = RefJenisDokumen::query()->find($refJenisDokumenId);
            if ($referensi !== null) {
                return [
                    'ref_jenis_dokumen_id' => $referensi->id,
                    'jenis_dokumen' => filled($jenisDokumen) ? $jenisDokumen : $referensi->nama,
                ];
            }
        }

        $namaJenis = trim((string) $jenisDokumen);
        $normalized = strtolower(str_replace('_', ' ', $namaJenis));
        $matchedId = null;

        if ($normalized !== '') {
            $matchedId = RefJenisDokumen::query()
                ->get(['id', 'nama'])
                ->first(fn (RefJenisDokumen $item): bool => strtolower(trim(str_replace('_', ' ', $item->nama))) === $normalized)
                ?->id;
        }

        return [
            'ref_jenis_dokumen_id' => $matchedId,
            'jenis_dokumen' => $namaJenis,
        ];
    }
}
