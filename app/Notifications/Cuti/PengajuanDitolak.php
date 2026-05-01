<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use Illuminate\Notifications\Notification;

class PengajuanDitolak extends Notification
{
    /**
     * Notifikasi untuk pegawai pemohon bahwa pengajuan cuti ditolak.
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
        $pengajuan = $this->pengajuan->loadMissing(['jenisCuti']);

        return [
            'title' => 'Pengajuan Cuti Ditolak',
            'body' => sprintf(
                'Pengajuan %s Anda ditolak. Alasan: %s',
                $pengajuan->jenisCuti?->nama ?? $pengajuan->jenis_cuti_kode,
                $pengajuan->rejection_reason ?? '-',
            ),
            'link' => "/cuti/pengajuan/{$pengajuan->id}",
            'pengajuan_id' => $pengajuan->id,
        ];
    }
}
