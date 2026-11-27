<?php

namespace App\Services\Cuti;

use App\Exceptions\Cuti\CancelTidakDiizinkanException;
use App\Exceptions\Cuti\TransitionTidakValidException;
use App\Jobs\SuratDinas\RegisterSuratDinasJob;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanApprovalStep;
use App\Models\Cuti\CutiPengajuanApproverHistory;
use App\Models\Cuti\CutiPengajuanStateHistory;
use App\Models\Cuti\CutiPerizinanEksternal;
use App\Models\Pegawai;
use App\Notifications\Cuti\PengajuanDisetujui;
use App\Notifications\Cuti\PengajuanDitolak;
use App\Notifications\Cuti\PengajuanMenungguApproval;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\SuratDinasRequestService;
use App\States\Cuti\DibatalkanState;
use App\States\Cuti\DicabutSetelahDisetujuiState;
use App\States\Cuti\DisetujuiAtasanState;
use App\States\Cuti\DisetujuiState;
use App\States\Cuti\DiteruskanInstansiAtasanState;
use App\States\Cuti\DitolakAtasanState;
use App\States\Cuti\DitolakInstansiAtasanState;
use App\States\Cuti\DitolakKepegawaianState;
use App\States\Cuti\DitolakPejabatState;
use App\States\Cuti\DiverifikasiState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function __construct(
        private SaldoLedgerService $saldoService,
        private EventDispatcherService $eventDispatcher,
        private ?SuratDinasRequestService $suratDinasService = null,
    ) {}

    /**
     * Verifikasi pengajuan oleh petugas kepegawaian.
     * Transisi: DIAJUKAN → DIVERIFIKASI
     */
    public function verify(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DiverifikasiState::class)) {
                throw new TransitionTidakValidException('Transisi ke DIVERIFIKASI tidak valid dari state saat ini.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.verify');
            $this->guardSelfApproval($aktor, $pengajuan);

            if ($pengajuan->petugas_kepegawaian_current_nip !== $aktor->nip) {
                throw new AuthorizationException('Anda bukan petugas kepegawaian yang ditunjuk untuk pengajuan ini.');
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DiverifikasiState::class);

            $this->logApprovalStep($pengajuan, 'petugas_kepegawaian', 'verify', $aktor, $catatan);
            $this->logStateHistory($pengajuan, $fromState, 'DIVERIFIKASI', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.diverifikasi', $pengajuan);

            // Kirim notifikasi ke atasan langsung
            $pengajuan->loadMissing(['atasanLangsungCurrent']);
            $atasan = $pengajuan->atasanLangsungCurrent;
            $atasan?->notify(new PengajuanMenungguApproval($pengajuan, 'atasan_langsung'));
        });
    }

    /**
     * Persetujuan oleh atasan langsung.
     * Transisi: DIVERIFIKASI → DISETUJUI_ATASAN
     */
    public function approveAtasan(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DisetujuiAtasanState::class)) {
                throw new TransitionTidakValidException('Transisi ke DISETUJUI_ATASAN tidak valid dari state saat ini.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.approve-langsung');
            $this->guardSelfApproval($aktor, $pengajuan);

            // Validasi aktor adalah atasan langsung yang ditunjuk
            if ($pengajuan->atasan_langsung_current_nip !== $aktor->nip) {
                throw new AuthorizationException('Anda bukan atasan langsung yang ditunjuk untuk pengajuan ini.');
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DisetujuiAtasanState::class);

            $this->logApprovalStep($pengajuan, 'atasan_langsung', 'approve', $aktor, $catatan);
            $this->logStateHistory($pengajuan, $fromState, 'DISETUJUI_ATASAN', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.disetujui_atasan', $pengajuan);

            // Kirim notifikasi ke pejabat berwenang
            $pengajuan->loadMissing(['pejabatBerwenangCurrent']);
            $pejabat = $pengajuan->pejabatBerwenangCurrent;
            $pejabat?->notify(new PengajuanMenungguApproval($pengajuan, 'pejabat_berwenang'));
        });
    }

    /**
     * Persetujuan oleh pejabat berwenang (final approval).
     * Transisi: DISETUJUI_ATASAN → DISETUJUI
     * Untuk CT: commit saldo ledger dari pending ke confirmed.
     */
    public function approvePejabat(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DisetujuiState::class)) {
                throw new TransitionTidakValidException('Transisi ke DISETUJUI tidak valid dari state saat ini.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.approve-pejabat');
            $this->guardSelfApproval($aktor, $pengajuan);

            if ($pengajuan->pejabat_berwenang_current_nip !== $aktor->nip) {
                throw new AuthorizationException('Anda bukan pejabat berwenang yang ditunjuk untuk pengajuan ini.');
            }

            $pengajuan->loadMissing(['pegawai.unitKerja', 'pegawai.jabatan', 'jenisCuti']);
            $kewenangan = $pengajuan->pegawai !== null
                ? $this->resolveKewenanganService()->resolve($pengajuan->pegawai, $pengajuan->jenis_cuti_kode)
                : null;

            // Jika kewenangan berada di instansi atasan (PTA/MA), teruskan tanpa commit saldo maupun nomor SiSukma.
            if ($kewenangan !== null) {
                $fromState = $pengajuan->state->name();
                $pengajuan->state->transitionTo(DiteruskanInstansiAtasanState::class);
                $pengajuan->save();

                CutiPerizinanEksternal::query()->firstOrCreate(
                    ['pengajuan_id' => $pengajuan->id],
                    [
                        'pemberi_izin' => $kewenangan->pemberi_izin,
                        'instansi_nama' => $kewenangan->instansi_nama,
                    ],
                );

                $this->logApprovalStep(
                    $pengajuan,
                    'pejabat_berwenang',
                    'approve',
                    $aktor,
                    $catatan ?: "Diteruskan ke {$kewenangan->instansi_nama}",
                );
                $this->logStateHistory(
                    $pengajuan,
                    $fromState,
                    'DITERUSKAN_INSTANSI_ATASAN',
                    $aktor,
                    $catatan ?: "Diteruskan ke {$kewenangan->instansi_nama}",
                );
                $this->eventDispatcher->dispatch('cuti.diteruskan', $pengajuan);

                return;
            }

            // Lock alokasi untuk CT dan commit saldo
            if ($pengajuan->jenis_cuti_kode === 'CT') {
                CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', '<=', $pengajuan->tahunHak())
                    ->lockForUpdate()
                    ->get();

                $this->saldoService->commitConfirmed($pengajuan);
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DisetujuiState::class);
            $pengajuan->approved_at = now();
            $pengajuan->save();

            $this->logApprovalStep($pengajuan, 'pejabat_berwenang', 'approve', $aktor, $catatan);
            $this->logStateHistory($pengajuan, $fromState, 'DISETUJUI', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.disetujui', $pengajuan);

            // Kirim notifikasi ke pegawai pemohon
            $pengajuan->loadMissing(['pegawai.unitKerja', 'jenisCuti']);
            $pengajuan->pegawai?->notify(new PengajuanDisetujui($pengajuan));

            if (config('sisukma.enabled')) {
                $suratDinas = $this->resolveSuratDinasService()->createForSuratable(
                    suratable: $pengajuan,
                    jenis: 'surat_izin_cuti',
                    klasifikasi: 'KP5.3',
                    penandatangan: (string) ($pengajuan->jenisCuti?->penandatangan_surat ?? 'ketua'),
                    documentType: 'surat_dinas',
                    perihal: sprintf(
                        'Surat Izin %s a.n. %s (%s)',
                        $pengajuan->jenisCuti?->nama ?? $pengajuan->jenis_cuti_kode,
                        $pengajuan->pegawai?->nama_lengkap ?? $pengajuan->pegawai_nip,
                        $pengajuan->nomor_pengajuan,
                    ),
                    tujuanNama: (string) ($pengajuan->pegawai?->nama_lengkap ?? $pengajuan->pegawai_nip),
                    tujuanInstansi: (string) ($pengajuan->pegawai?->unitKerja?->nama ?? config('sikep.nama_satker', 'Pengadilan Agama Penajam')),
                    requestedByNip: $aktor->nip,
                    externalPath: "/cuti/pengajuan/{$pengajuan->id}",
                );

                DB::afterCommit(static fn () => RegisterSuratDinasJob::dispatch($suratDinas->id));
            }
        });
    }

    /**
     * Penolakan generik untuk 3 role: kepegawaian, atasan, pejabat.
     * Untuk CT: void pending ledger entries.
     */
    public function rejectByRole(string $id, Pegawai $aktor, string $role, string $alasan): void
    {
        DB::transaction(function () use ($id, $aktor, $role, $alasan): void {
            $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();

            $targetState = match ($role) {
                'petugas_kepegawaian' => DitolakKepegawaianState::class,
                'atasan_langsung' => DitolakAtasanState::class,
                'pejabat_berwenang' => DitolakPejabatState::class,
                default => throw new \InvalidArgumentException("Role tidak dikenali: {$role}"),
            };

            if (! $pengajuan->state->canTransitionTo($targetState)) {
                throw new TransitionTidakValidException('Transisi penolakan tidak valid dari state saat ini.');
            }

            $permMap = [
                'petugas_kepegawaian' => 'cuti.pengajuan.verify',
                'atasan_langsung' => 'cuti.pengajuan.approve-langsung',
                'pejabat_berwenang' => 'cuti.pengajuan.approve-pejabat',
            ];
            $this->guardAuthorization($aktor, $permMap[$role]);
            $this->guardSelfApproval($aktor, $pengajuan);

            $currentNipCol = "{$role}_current_nip";
            if ($aktor->nip !== $pengajuan->$currentNipCol) {
                throw new AuthorizationException('Anda bukan approver yang ditunjuk untuk pengajuan ini.');
            }

            // Void pending ledger untuk CT
            if ($pengajuan->jenis_cuti_kode === 'CT') {
                $this->saldoService->voidPending($pengajuan);
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo($targetState);
            $pengajuan->rejected_at = now();
            $pengajuan->rejection_reason = $alasan;
            $pengajuan->save();

            $this->logApprovalStep($pengajuan, $role, 'reject', $aktor, $alasan);
            $this->logStateHistory($pengajuan, $fromState, $pengajuan->state->name(), $aktor, $alasan);
            $this->eventDispatcher->dispatch('cuti.ditolak', $pengajuan);

            // Kirim notifikasi ke pegawai pemohon
            $pengajuan->loadMissing(['pegawai']);
            $pengajuan->pegawai?->notify(new PengajuanDitolak($pengajuan));
        });
    }

    /**
     * Pembatalan pengajuan dalam state DRAFT.
     * Tidak ada interaksi ledger karena belum ada debit.
     */
    public function cancelDraft(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DibatalkanState::class)) {
                throw new TransitionTidakValidException('Transisi ke DIBATALKAN tidak valid dari state saat ini.');
            }

            // Harus pemilik pengajuan + punya permission cancel-own, atau admin cancel-any
            $isOwner = $aktor->nip === $pengajuan->pegawai_nip;
            if ($isOwner && $aktor->hasPermission('cuti.pengajuan.cancel-own')) {
                // ok — pemilik membatalkan miliknya
            } elseif ($aktor->hasPermission('cuti.pengajuan.cancel-any')) {
                // ok — admin membatalkan
            } else {
                throw new AuthorizationException('Tidak berhak membatalkan pengajuan ini.');
            }

            $fromState = $pengajuan->state->name();

            // Jika dibatalkan saat menunggu keputusan instansi atasan, kembalikan saldo pending CT.
            if ($fromState === 'DITERUSKAN_INSTANSI_ATASAN' && $pengajuan->jenis_cuti_kode === 'CT') {
                CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', '<=', $pengajuan->tahunHak())
                    ->lockForUpdate()
                    ->get();

                $this->saldoService->voidPending($pengajuan);
            }

            $pengajuan->state->transitionTo(DibatalkanState::class);
            $pengajuan->cancelled_at = now();
            $pengajuan->save();

            $this->logStateHistory($pengajuan, $fromState, 'DIBATALKAN', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.dibatalkan', $pengajuan);
        });
    }

    /**
     * Pencabutan cuti yang sudah disetujui.
     * Guard: cek boleh_dicabut_setelah_disetujui pada jenis cuti.
     * Untuk CT: proses refund saldo.
     */
    public function cancelAfterApproved(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DicabutSetelahDisetujuiState::class)) {
                throw new TransitionTidakValidException('Transisi ke DICABUT_SETELAH_DISETUJUI tidak valid dari state saat ini.');
            }

            // Pemilik (cancel-own) atau admin (cancel-any)
            $isOwner = $aktor->nip === $pengajuan->pegawai_nip;
            if ($isOwner && $aktor->hasPermission('cuti.pengajuan.cancel-own')) {
                // ok
            } elseif ($aktor->hasPermission('cuti.pengajuan.cancel-any')) {
                // ok
            } else {
                throw new AuthorizationException('Tidak berhak membatalkan cuti yang sudah disetujui.');
            }

            // Cek apakah jenis cuti boleh dicabut setelah disetujui
            $pengajuan->loadMissing(['jenisCuti']);
            $jenisCuti = $pengajuan->jenisCuti;
            if (! $jenisCuti->boleh_dicabut_setelah_disetujui) {
                throw new CancelTidakDiizinkanException(
                    "Jenis cuti {$jenisCuti->nama} tidak boleh dicabut setelah disetujui."
                );
            }

            // Blokir pencabutan jika periode cuti sudah berlalu
            if ($pengajuan->tanggal_selesai !== null && Date::today()->gt($pengajuan->tanggal_selesai->startOfDay())) {
                throw new CancelTidakDiizinkanException(
                    'Cuti yang sudah melewati tanggal selesai tidak dapat dicabut.'
                );
            }

            // Refund saldo untuk CT
            if ($pengajuan->jenis_cuti_kode === 'CT') {
                $this->saldoService->processRefund($pengajuan);
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DicabutSetelahDisetujuiState::class);
            $pengajuan->cancelled_at = now();
            $pengajuan->save();

            $this->logStateHistory($pengajuan, $fromState, 'DICABUT_SETELAH_DISETUJUI', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.dicabut', $pengajuan);

            if (config('sisukma.enabled')) {
                $pengajuan->loadMissing('suratIzinRequest');
                $surat = $pengajuan->suratIzinRequest;

                if ($surat !== null && $surat->status->canCancelBeforeNumbered()) {
                    $this->resolveSuratDinasService()->cancelIfUnnumbered($surat, $catatan ?: 'Cuti dicabut setelah disetujui');
                }
            }
        });
    }

    /**
     * Mencatat nomor registrasi dan tanggal pengajuan manual di aplikasi PTA/MA.
     *
     * @param  array<string, mixed>  $data
     */
    public function catatRegistrasiEksternal(string $pengajuanId, Pegawai $aktor, array $data): CutiPerizinanEksternal
    {
        return DB::transaction(function () use ($pengajuanId, $aktor, $data): CutiPerizinanEksternal {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if ($pengajuan->state->name() !== 'DITERUSKAN_INSTANSI_ATASAN') {
                throw new TransitionTidakValidException('Pencatatan registrasi eksternal hanya berlaku saat menunggu instansi atasan.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.verify');

            $eksternal = CutiPerizinanEksternal::query()
                ->where('pengajuan_id', $pengajuan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $eksternal->update([
                'nomor_registrasi_eksternal' => $data['nomor_registrasi_eksternal'],
                'tanggal_pengajuan_eksternal' => $data['tanggal_pengajuan_eksternal'],
                'catatan_pengajuan' => $data['catatan_pengajuan'] ?? null,
                'recorded_by_nip' => $aktor->nip,
            ]);

            $this->logStateHistory(
                $pengajuan,
                'DITERUSKAN_INSTANSI_ATASAN',
                'DITERUSKAN_INSTANSI_ATASAN',
                $aktor,
                "Registrasi eksternal dicatat: {$eksternal->nomor_registrasi_eksternal}",
            );

            return $eksternal->fresh();
        });
    }

    /**
     * Mencatat keputusan akhir PTA/MA dengan menautkan Surat Masuk SiSukma sebagai arsip SK satu pintu.
     *
     * @param  array<string, mixed>  $data
     */
    public function catatKeputusanEksternal(string $pengajuanId, Pegawai $aktor, array $data): CutiPerizinanEksternal
    {
        $this->guardAuthorization($aktor, 'cuti.pengajuan.verify');

        $pengajuanPreview = CutiPengajuan::query()->findOrFail($pengajuanId);
        if ($pengajuanPreview->state->name() !== 'DITERUSKAN_INSTANSI_ATASAN') {
            throw new TransitionTidakValidException('Keputusan eksternal hanya dapat dicatat pada pengajuan berstatus menunggu instansi atasan.');
        }

        // Tautkan surat masuk di SiSukma terlebih dahulu sebelum mengunci/mengubah state lokal.
        $linkResult = $this->resolveGateway()->linkIncomingMail(
            publicId: (string) $data['sisukma_incoming_mail_public_id'],
            externalReference: "cuti-ext:{$pengajuanPreview->id}",
            externalLabel: (string) ($pengajuanPreview->nomor_pengajuan ?? $pengajuanPreview->id),
            externalPath: "/cuti/pengajuan/{$pengajuanPreview->id}",
            linkedByNip: $aktor->nip,
        );

        return DB::transaction(function () use ($pengajuanId, $aktor, $data, $linkResult): CutiPerizinanEksternal {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if ($pengajuan->state->name() !== 'DITERUSKAN_INSTANSI_ATASAN') {
                throw new TransitionTidakValidException('Status pengajuan berubah saat pencatatan keputusan eksternal.');
            }

            $eksternal = CutiPerizinanEksternal::query()
                ->where('pengajuan_id', $pengajuan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $keputusan = (string) $data['keputusan'];
            $catatan = $data['catatan_keputusan'] ?? null;

            $eksternal->update([
                'sisukma_incoming_mail_public_id' => $data['sisukma_incoming_mail_public_id'],
                'sisukma_agenda_number' => $linkResult['agenda_number'] ?? $eksternal->sisukma_agenda_number,
                'nomor_sk' => $data['nomor_sk'],
                'tanggal_sk' => $data['tanggal_sk'],
                'keputusan' => $keputusan,
                'catatan_keputusan' => $catatan,
                'recorded_by_nip' => $aktor->nip,
                'decided_at' => now(),
            ]);

            if ($keputusan === 'disetujui') {
                if ($pengajuan->jenis_cuti_kode === 'CT') {
                    CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
                        ->where('jenis_cuti_kode', 'CT')
                        ->where('tahun_hak', '<=', $pengajuan->tahunHak())
                        ->lockForUpdate()
                        ->get();

                    $this->saldoService->commitConfirmed($pengajuan);
                }

                $pengajuan->state->transitionTo(DisetujuiState::class);
                $pengajuan->approved_at = now();
                $pengajuan->save();

                $this->logStateHistory(
                    $pengajuan,
                    'DITERUSKAN_INSTANSI_ATASAN',
                    'DISETUJUI',
                    $aktor,
                    $catatan ?: "Disetujui instansi atasan ({$eksternal->nomor_sk})",
                );
                $this->eventDispatcher->dispatch('cuti.disetujui', $pengajuan);

                $pengajuan->loadMissing(['pegawai.unitKerja', 'jenisCuti']);
                $pengajuan->pegawai?->notify(new PengajuanDisetujui($pengajuan));
            } else {
                if ($pengajuan->jenis_cuti_kode === 'CT') {
                    CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
                        ->where('jenis_cuti_kode', 'CT')
                        ->where('tahun_hak', '<=', $pengajuan->tahunHak())
                        ->lockForUpdate()
                        ->get();

                    $this->saldoService->voidPending($pengajuan);
                }

                $pengajuan->state->transitionTo(DitolakInstansiAtasanState::class);
                $pengajuan->rejected_at = now();
                $pengajuan->rejection_reason = $catatan ?: "Ditolak instansi atasan ({$eksternal->nomor_sk})";
                $pengajuan->save();

                $this->logStateHistory(
                    $pengajuan,
                    'DITERUSKAN_INSTANSI_ATASAN',
                    'DITOLAK_INSTANSI_ATASAN',
                    $aktor,
                    $pengajuan->rejection_reason,
                );
                $this->eventDispatcher->dispatch('cuti.ditolak', $pengajuan);

                $pengajuan->loadMissing(['pegawai', 'jenisCuti']);
                $pengajuan->pegawai?->notify(new PengajuanDitolak($pengajuan));
            }

            return $eksternal->fresh();
        });
    }

    /**
     * Mengambil layanan surat dinas secara lazy agar konstruktor tetap kompatibel mundur.
     */
    private function resolveSuratDinasService(): SuratDinasRequestService
    {
        return $this->suratDinasService ??= app(SuratDinasRequestService::class);
    }

    /**
     * Mengambil layanan matriks kewenangan secara lazy.
     */
    private function resolveKewenanganService(): KewenanganResolverService
    {
        return app(KewenanganResolverService::class);
    }

    /**
     * Mengambil gateway SiSukma secara lazy untuk penautan surat masuk.
     */
    private function resolveGateway(): PenomoranSuratGateway
    {
        return app(PenomoranSuratGateway::class);
    }

    /**
     * Reassign approver pada pengajuan aktif.
     * Hanya admin yang boleh melakukan reassignment.
     */
    public function reassignApprover(string $id, string $role, string $newNip, Pegawai $aktor, string $alasan): void
    {
        $allowedRoles = ['petugas_kepegawaian', 'atasan_langsung', 'pejabat_berwenang'];

        if (! in_array($role, $allowedRoles, true)) {
            throw new \InvalidArgumentException("Role tidak valid untuk reassignment: {$role}");
        }

        DB::transaction(function () use ($id, $role, $newNip, $aktor, $alasan): void {
            $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();
            $this->guardAuthorization($aktor, 'cuti.pengajuan.reassign');

            $col = "{$role}_current_nip";
            $oldNip = $pengajuan->$col;
            $pengajuan->$col = $newNip;
            $pengajuan->save();

            CutiPengajuanApproverHistory::create([
                'pengajuan_id' => $id,
                'role' => $role,
                'from_pegawai_nip' => $oldNip,
                'to_pegawai_nip' => $newNip,
                'alasan' => $alasan,
                'aktor_pegawai_nip' => $aktor->nip,
            ]);
        });
    }

    /**
     * Mencegah approver menyetujui/memverifikasi/menolak pengajuan miliknya sendiri.
     *
     * @throws AuthorizationException
     */
    private function guardSelfApproval(Pegawai $aktor, CutiPengajuan $pengajuan): void
    {
        if ($aktor->nip === $pengajuan->pegawai_nip) {
            throw new AuthorizationException('Anda tidak dapat memproses persetujuan pengajuan cuti milik sendiri.');
        }
    }

    /**
     * Validasi authorization berdasarkan permission IAM.
     *
     * @throws AuthorizationException
     */
    private function guardAuthorization(Pegawai $aktor, string $permission): void
    {
        if (! $aktor->hasPermission($permission)) {
            throw new AuthorizationException("Tidak memiliki permission: {$permission}");
        }
    }

    /**
     * Catat langkah approval ke tabel audit.
     */
    private function logApprovalStep(
        CutiPengajuan $p,
        string $role,
        string $action,
        Pegawai $aktor,
        ?string $catatan = null,
    ): void {
        CutiPengajuanApprovalStep::create([
            'pengajuan_id' => $p->id,
            'role' => $role,
            'action' => $action,
            'aktor_pegawai_nip' => $aktor->nip,
            'catatan' => $catatan,
            'acted_at' => now(),
        ]);
    }

    /**
     * Catat perubahan state ke tabel history.
     */
    private function logStateHistory(
        CutiPengajuan $p,
        string $from,
        string $to,
        Pegawai $aktor,
        ?string $catatan = null,
    ): void {
        CutiPengajuanStateHistory::create([
            'pengajuan_id' => $p->id,
            'state_from' => $from,
            'state_to' => $to,
            'aktor_pegawai_nip' => $aktor->nip,
            'catatan' => $catatan,
        ]);
    }
}
