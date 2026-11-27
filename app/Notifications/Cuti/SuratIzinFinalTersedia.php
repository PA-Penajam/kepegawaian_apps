<?php

namespace App\Notifications\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\Notifications\Channels\SisukmaChannel;
use App\Notifications\Contracts\HasSisukmaNotification;
use Illuminate\Notifications\Notification;

class SuratIzinFinalTersedia extends Notification implements HasSisukmaNotification
{
    public function __construct(
        private readonly CutiPengajuan $pengajuan,
        private readonly ?string $nomorSurat = null,
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
            'title' => 'Surat Izin Cuti Final Tersedia',
            'body' => sprintf(
                'Berkas final Surat Izin %s%s sudah ditandatangani dan siap diunduh.',
                $pengajuan->jenisCuti?->nama ?? $pengajuan->jenis_cuti_kode,
                filled($this->nomorSurat) ? " ({$this->nomorSurat})" : '',
            ),
            'link' => "/cuti/pengajuan/{$pengajuan->id}",
            'pengajuan_id' => $pengajuan->id,
            'nomor_surat' => $this->nomorSurat,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSisukma(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);

        return [
            'idempotency_key' => "cuti:surat-izin-final:{$this->pengajuan->id}",
            'title' => $data['title'],
            'message' => $data['body'],
            'action_path' => $data['link'],
            'type' => 'cuti_final_tersedia',
            'metadata' => [
                'No. Pengajuan' => $this->pengajuan->nomor_pengajuan,
                'No. Surat' => $this->nomorSurat,
            ],
        ];
    }
}
