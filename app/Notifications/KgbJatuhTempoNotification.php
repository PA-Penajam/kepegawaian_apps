<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KgbJatuhTempoNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Carbon $kgbDate,
        private readonly int $sisaHari,
        private readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nama = $notifiable->nama_lengkap ?? 'Pegawai';
        $tanggal = $this->kgbDate->translatedFormat('d F Y');

        $subject = $this->sisaHari <= 0
            ? "KGB Sudah Jatuh Tempo — {$tanggal}"
            : "Pengingat KGB Mendekati Jatuh Tempo — {$tanggal}";

        $introLine = $this->sisaHari <= 0
            ? "Kenaikan Gaji Berkala (KGB) Anda telah jatuh tempo pada {$tanggal}."
            : "Kenaikan Gaji Berkala (KGB) Anda akan jatuh tempo pada {$tanggal} ({$this->sisaHari} hari lagi).";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Yth. {$nama},")
            ->line($introLine)
            ->line("Status KGB: **{$this->status}**")
            ->line('Harap segera mengurus dokumen KGB ke bagian kepegawaian.')
            ->action('Lihat Monitoring KGB', url('/kepegawaian/monitoring/kgb'))
            ->salutation('Hormat kami, Sistem Kepegawaian');
    }
}
