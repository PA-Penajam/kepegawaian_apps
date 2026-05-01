<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use Illuminate\Notifications\Notification;

class PengajuanMenungguVerifikasi extends Notification
{
    /**
     * Notifikasi untuk petugas kepegawaian bahwa ada pengajuan baru menunggu verifikasi.
     */
    public function __construct(
        private readonly CutiPengajuan $pengajuan,
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

        return [
            'title' => 'Pengajuan Cuti Menunggu Verifikasi',
            'body' => sprintf(
                '%s mengajukan %s (%s - %s)',
                $pengajuan->pegawai?->nama_lengkap ?? $pengajuan->pegawai_nip,
                $pengajuan->jenisCuti?->nama ?? $pengajuan->jenis_cuti_kode,
                $pengajuan->tanggal_mulai?->format('d/m/Y'),
                $pengajuan->tanggal_selesai?->format('d/m/Y'),
            ),
            'link' => "/cuti/pengajuan/{$pengajuan->id}",
            'pengajuan_id' => $pengajuan->id,
        ];
    }
}
