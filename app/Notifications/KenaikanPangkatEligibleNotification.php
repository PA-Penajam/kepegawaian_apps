<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KenaikanPangkatEligibleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $periodeBulan,
        public readonly int $periodeTahun,
        public readonly string $batasUsul,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nama = $notifiable->nama_lengkap ?? 'Pegawai';
        $namaBulan = Carbon::createFromDate($this->periodeTahun, $this->periodeBulan, 1)->translatedFormat('F');
        $batas = $this->batasUsul;

        return (new MailMessage)
            ->subject("Kenaikan Pangkat Eligible — Periode {$namaBulan} {$this->periodeTahun}")
            ->greeting("Yth. {$nama},")
            ->line("Anda eligible kenaikan pangkat periode {$namaBulan} {$this->periodeTahun}. Batas usul: {$batas}.")
            ->line('Harap segera mengurus dokumen kenaikan pangkat ke bagian kepegawaian.')
            ->action('Lihat Monitoring KP', url('/kepegawaian/monitoring/kenaikan-pangkat'))
            ->salutation('Hormat kami, Sistem Kepegawaian');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'periode_bulan' => $this->periodeBulan,
            'periode_tahun' => $this->periodeTahun,
            'batas_usul' => $this->batasUsul,
            'message' => 'Pengingat kenaikan pangkat',
        ];
    }
}
