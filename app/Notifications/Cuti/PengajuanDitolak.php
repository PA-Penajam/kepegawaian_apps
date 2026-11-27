<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\Notifications\Channels\SisukmaChannel;
use App\Notifications\Contracts\HasSisukmaNotification;
use Illuminate\Notifications\Notification;

class PengajuanDitolak extends Notification implements HasSisukmaNotification
{
    /**
     * Notifikasi untuk pegawai pemohon bahwa pengajuan cuti ditolak.
     */
    public function __construct(
        private readonly CutiPengajuan $pengajuan,
    ) {}

    public function via(object $notifiable): array
    {
        return config('sisukma.notifications_enabled')
            ? ['database', SisukmaChannel::class]
            : ['database'];
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

    /**
     * @return array<string, mixed>
     */
    public function toSisukma(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);

        return [
            'idempotency_key' => "cuti:ditolak:{$this->pengajuan->id}",
            'title' => $data['title'],
            'message' => $data['body'],
            'action_path' => $data['link'],
            'type' => 'cuti_ditolak',
            'metadata' => [
                'No. Pengajuan' => $this->pengajuan->nomor_pengajuan,
            ],
        ];
    }
}
