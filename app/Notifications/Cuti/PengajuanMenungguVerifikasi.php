<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\Notifications\Channels\SisukmaChannel;
use App\Notifications\Contracts\HasSisukmaNotification;
use Illuminate\Notifications\Notification;

class PengajuanMenungguVerifikasi extends Notification implements HasSisukmaNotification
{
    /**
     * Notifikasi untuk petugas kepegawaian bahwa ada pengajuan baru menunggu verifikasi.
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

    /**
     * @return array<string, mixed>
     */
    public function toSisukma(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);

        return [
            'idempotency_key' => "cuti:menunggu-verifikasi:{$this->pengajuan->id}",
            'title' => $data['title'],
            'message' => $data['body'],
            'action_path' => $data['link'],
            'type' => 'cuti_menunggu_verifikasi',
            'metadata' => [
                'No. Pengajuan' => $this->pengajuan->nomor_pengajuan,
            ],
        ];
    }
}
