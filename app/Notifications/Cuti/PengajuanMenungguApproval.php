<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use Illuminate\Notifications\Notification;

class PengajuanMenungguApproval extends Notification
{
    /**
     * Notifikasi untuk atasan langsung atau pejabat berwenang bahwa pengajuan menunggu persetujuan.
     */
    public function __construct(
        private readonly CutiPengajuan $pengajuan,
        private readonly string $targetRole,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $pengajuan = $this->pengajuan->loadMissing(['pegawai', 'jenisCuti']);

        $roleLabel = match ($this->targetRole) {
            'atasan_langsung' => 'Atasan Langsung',
            'pejabat_berwenang' => 'Pejabat Berwenang',
            default => $this->targetRole,
        };

        return [
            'title' => 'Pengajuan Cuti Menunggu Persetujuan',
            'body' => sprintf(
                '%s mengajukan %s (%s - %s) — menunggu persetujuan %s',
                $pengajuan->pegawai?->nama_lengkap ?? $pengajuan->pegawai_nip,
                $pengajuan->jenisCuti?->nama ?? $pengajuan->jenis_cuti_kode,
                $pengajuan->tanggal_mulai?->format('d/m/Y'),
                $pengajuan->tanggal_selesai?->format('d/m/Y'),
                $roleLabel,
            ),
            'link' => "/cuti/pengajuan/{$pengajuan->id}",
            'pengajuan_id' => $pengajuan->id,
            'target_role' => $this->targetRole,
        ];
    }
}
