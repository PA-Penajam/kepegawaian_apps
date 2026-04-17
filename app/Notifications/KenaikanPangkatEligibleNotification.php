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
        private readonly Carbon $tmtKpBerikutnya,
        private readonly string $periodeUsul,
        private readonly Carbon $batasUsul,
        private readonly int $sisaHariUsul,
        private readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nama = $notifiable->nama_lengkap ?? 'Pegawai';
        $tanggalKp = $this->tmtKpBerikutnya->translatedFormat('d F Y');
        $batasUsulFormatted = $this->batasUsul->translatedFormat('d F Y');

        $subject = $this->status === 'Sudah Eligible'
            ? "Kenaikan Pangkat Eligible — Periode {$this->periodeUsul}"
            : "Pengingat Kenaikan Pangkat Mendekati Eligible — Periode {$this->periodeUsul}";

        $introLine = $this->status === 'Sudah Eligible'
            ? "Kenaikan pangkat Anda dengan TMT {$tanggalKp} sudah eligible untuk diajukan pada periode {$this->periodeUsul}."
            : "Kenaikan pangkat Anda dengan TMT {$tanggalKp} akan eligible pada periode {$this->periodeUsul} ({$this->sisaHariUsul} hari lagi).";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Yth. {$nama},")
            ->line($introLine)
            ->line("Status KP: **{$this->status}**")
            ->line("Batas usul: {$batasUsulFormatted}")
            ->line('Harap segera mengurus dokumen kenaikan pangkat ke bagian kepegawaian.')
            ->action('Lihat Monitoring KP', url('/kepegawaian/monitoring/kenaikan-pangkat'))
            ->salutation('Hormat kami, Sistem Kepegawaian');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tmt_kp_berikutnya' => $this->tmtKpBerikutnya->toDateString(),
            'periode_usul' => $this->periodeUsul,
            'batas_usul' => $this->batasUsul->toDateString(),
            'sisa_hari_usul' => $this->sisaHariUsul,
            'status' => $this->status,
        ];
    }
}
