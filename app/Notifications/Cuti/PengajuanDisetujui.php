<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use Illuminate\Notifications\Notification;

class PengajuanDisetujui extends Notification
{
    /**
     * Notifikasi untuk pegawai pemohon bahwa pengajuan cuti telah disetujui.
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
            'title' => 'Pengajuan Cuti Disetujui',
            'body' => sprintf(
                'Pengajuan %s Anda (%s - %s) telah disetujui',
                $pengajuan->jenisCuti?->nama ?? $pengajuan->jenis_cuti_kode,
                $pengajuan->tanggal_mulai?->format('d/m/Y'),
                $pengajuan->tanggal_selesai?->format('d/m/Y'),
            ),
            'link' => "/cuti/pengajuan/{$pengajuan->id}",
            'pengajuan_id' => $pengajuan->id,
        ];
    }
}
