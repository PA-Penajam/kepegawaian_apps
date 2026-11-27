<?php

namespace App\Services\SuratDinas;

use App\Enums\StatusSuratDinas;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\Sisukma\Exceptions\SisukmaException;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\Contracts\SuratDinasLifecycleHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Layanan tunggal pengelola siklus hidup permintaan nomor & arsip surat dinas ke SiSukma.
 */
class SuratDinasRequestService
{
    public function __construct(
        private readonly PenomoranSuratGateway $gateway,
    ) {}

    /**
     * Membuat entri permintaan surat dinas baru secara idempoten per (suratable, jenis).
     */
    public function createForSuratable(
        Model $suratable,
        string $jenis,
        string $klasifikasi,
        string $penandatangan,
        string $documentType,
        string $perihal,
        string $tujuanNama,
        ?string $tujuanInstansi,
        string $requestedByNip,
        ?string $externalPath = null,
        ?string $tujuanAlamat = null,
    ): SuratDinasRequest {
        return SuratDinasRequest::query()->firstOrCreate(
            [
                'suratable_type' => $suratable->getMorphClass(),
                'suratable_id' => $suratable->getKey(),
                'jenis' => $jenis,
            ],
            [
                'klasifikasi' => $klasifikasi,
                'penandatangan' => $penandatangan,
                'document_type' => $documentType,
                'perihal' => $perihal,
                'tujuan_nama' => $tujuanNama,
                'tujuan_instansi' => $tujuanInstansi,
                'tujuan_alamat' => $tujuanAlamat,
                'external_path' => $externalPath,
                'status' => StatusSuratDinas::BelumTerkirim,
                'requested_by_nip' => $requestedByNip,
                'attempts' => 0,
            ],
        );
    }

    /**
     * Mendaftarkan permintaan ke gateway SiSukma secara idempoten.
     */
    public function registerToGateway(SuratDinasRequest $request): SuratDinasRequest
    {
        if (filled($request->sisukma_public_id) && $request->status !== StatusSuratDinas::BelumTerkirim) {
            return $request;
        }

        try {
            $result = $this->gateway->register($request);

            $request->forceFill([
                'sisukma_public_id' => $result->publicId,
                'nomor_agenda' => $result->agendaNumber,
                'status' => StatusSuratDinas::MenungguNomor,
                'attempts' => $request->attempts + 1,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $request->forceFill([
                'attempts' => $request->attempts + 1,
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $request;
    }

    /**
     * Memperbarui metadata surat dinas yang perlu perbaikan lalu mengajukannya ulang ke SiSukma.
     *
     * @param  array<string, mixed>  $updates
     */
    public function resubmit(SuratDinasRequest $request, array $updates): SuratDinasRequest
    {
        if ($request->status !== StatusSuratDinas::PerluPerbaikan) {
            throw new \DomainException('Surat dinas tidak sedang dalam status perlu perbaikan.');
        }

        $allowed = array_intersect_key($updates, array_flip([
            'perihal',
            'tujuan_nama',
            'tujuan_instansi',
            'tujuan_alamat',
            'penandatangan',
            'document_type',
            'klasifikasi',
        ]));

        DB::transaction(function () use ($request, $allowed): void {
            $locked = SuratDinasRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StatusSuratDinas::PerluPerbaikan) {
                throw new \DomainException('Surat dinas tidak sedang dalam status perlu perbaikan.');
            }

            $locked->fill($allowed);
            $locked->save();

            $this->gateway->resubmit($locked);

            $locked->forceFill([
                'status' => StatusSuratDinas::MenungguNomor,
                'last_error' => null,
            ])->save();
        });

        return $request->refresh();
    }

    /**
     * Membatalkan surat di SiSukma apabila nomor resminya belum terbit.
     */
    public function cancelIfUnnumbered(SuratDinasRequest $request, string $reason): bool
    {
        if (! $request->status->canCancelBeforeNumbered()) {
            return false;
        }

        if (filled($request->sisukma_public_id)) {
            $this->gateway->cancel($request, $reason);
        }

        $request->forceFill([
            'status' => StatusSuratDinas::Dibatalkan,
            'alasan_void' => $reason,
        ])->save();

        return true;
    }

    /**
     * Memproses event terbitnya nomor surat dari SiSukma dan memicu hook per jenis.
     */
    public function applyNumberIssued(SuratDinasRequest $request, string $nomorSurat, ?string $nomorAgenda = null): SuratDinasRequest
    {
        $changed = DB::transaction(function () use ($request, $nomorSurat, $nomorAgenda): bool {
            $locked = SuratDinasRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->hasFinalFile() || $locked->status === StatusSuratDinas::Dibatalkan) {
                return false;
            }

            if ($locked->status === StatusSuratDinas::NomorTerbit && $locked->nomor_surat === $nomorSurat) {
                return false;
            }

            $locked->forceFill([
                'status' => StatusSuratDinas::NomorTerbit,
                'nomor_surat' => $nomorSurat,
                'nomor_agenda' => $nomorAgenda ?? $locked->nomor_agenda,
                'catatan_revisi' => null,
            ])->save();

            return true;
        });

        $fresh = $request->refresh();

        if ($changed) {
            $this->dispatchLifecycleHooks('onNumberIssued', $fresh);
        }

        return $fresh;
    }

    /**
     * Memproses permintaan perbaikan dari Admin TU SiSukma.
     */
    public function applyRevisionRequested(SuratDinasRequest $request, string $catatanRevisi): SuratDinasRequest
    {
        $request->forceFill([
            'status' => StatusSuratDinas::PerluPerbaikan,
            'catatan_revisi' => $catatanRevisi,
        ])->save();

        return $request->refresh();
    }

    /**
     * Memproses penolakan permanen (void) dari Admin TU SiSukma.
     */
    public function applyVoided(SuratDinasRequest $request, string $alasanVoid): SuratDinasRequest
    {
        $request->forceFill([
            'status' => StatusSuratDinas::Dibatalkan,
            'alasan_void' => $alasanVoid,
        ])->save();

        return $request->refresh();
    }

    /**
     * Memproses diterimanya berkas final bertanda tangan di SiSukma.
     */
    public function applyLetterAccepted(SuratDinasRequest $request, ?string $checksum, ?int $sizeBytes): SuratDinasRequest
    {
        $changed = DB::transaction(function () use ($request, $checksum, $sizeBytes): bool {
            $locked = SuratDinasRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === StatusSuratDinas::Terkirim) {
                return false;
            }

            if ($locked->status === StatusSuratDinas::FinalTersedia && $locked->final_checksum === $checksum) {
                return false;
            }

            $locked->forceFill([
                'status' => StatusSuratDinas::FinalTersedia,
                'final_checksum' => $checksum,
                'final_size_bytes' => $sizeBytes,
                'final_uploaded_at' => $locked->final_uploaded_at ?? now(),
            ])->save();

            return true;
        });

        $fresh = $request->refresh();

        if ($changed) {
            $this->dispatchLifecycleHooks('onLetterAccepted', $fresh);
        }

        return $fresh;
    }

    /**
     * Memproses status pengiriman/ekspedisi surat dari SiSukma.
     */
    public function applySent(SuratDinasRequest $request): SuratDinasRequest
    {
        $request->forceFill([
            'status' => StatusSuratDinas::Terkirim,
        ])->save();

        return $request->refresh();
    }

    /**
     * Mengunggah berkas draf PDF lokal ke `file_draft` SiSukma lalu membersihkan salinan sementara bila tidak dibutuhkan.
     */
    public function pushDraftFile(SuratDinasRequest $request, string $localRelativePath): void
    {
        $absolutePath = Storage::disk('local')->path($localRelativePath);

        if (! file_exists($absolutePath)) {
            throw new SisukmaException("Berkas draf lokal tidak ditemukan: {$localRelativePath}");
        }

        $this->gateway->replaceDraft($request, $absolutePath);
    }

    /**
     * Menyelaraskan satu permintaan surat yang tertinggal dengan memanggil `fetchStatus()` SiSukma.
     */
    public function reconcile(SuratDinasRequest $request): void
    {
        if ($request->status === StatusSuratDinas::BelumTerkirim) {
            $this->registerToGateway($request);

            return;
        }

        if (blank($request->sisukma_public_id)) {
            return;
        }

        $remote = $this->gateway->fetchStatus($request);

        match ($remote->status) {
            'needs_revision' => $this->applyRevisionRequested($request, (string) ($remote->revisionNote ?? 'Perlu perbaikan.')),
            'void' => $this->applyVoided($request, (string) ($remote->voidReason ?? 'Dibatalkan di SiSukma.')),
            'awaiting_file' => $remote->mailNumber ? $this->applyNumberIssued($request, $remote->mailNumber, $remote->agendaNumber) : null,
            'approved' => $this->reconcileApprovedOrSent($request, $remote->mailNumber, $remote->agendaNumber, false),
            'sent' => $this->reconcileApprovedOrSent($request, $remote->mailNumber, $remote->agendaNumber, true),
            default => null,
        };
    }

    private function reconcileApprovedOrSent(SuratDinasRequest $request, ?string $mailNumber, ?string $agendaNumber, bool $sent): void
    {
        if ($mailNumber !== null && $request->nomor_surat === null) {
            $this->applyNumberIssued($request, $mailNumber, $agendaNumber);
            $request->refresh();
        }

        $this->applyLetterAccepted($request, $request->final_checksum, $request->final_size_bytes);

        if ($sent) {
            $this->applySent($request->refresh());
        }
    }

    private function dispatchLifecycleHooks(string $method, SuratDinasRequest $request): void
    {
        $hooks = config('sisukma.lifecycle_hooks', []);
        if (! is_array($hooks)) {
            return;
        }

        foreach ($hooks as $hookBinding) {
            if (! is_string($hookBinding) || $hookBinding === '') {
                continue;
            }

            $hook = app($hookBinding);
            if ($hook instanceof SuratDinasLifecycleHook && $hook->supports($request)) {
                $hook->{$method}($request);
            }
        }
    }
}
