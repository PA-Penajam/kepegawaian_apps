<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KenaikanPangkatDeadlineNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $usulanId,
        public readonly CarbonInterface $batasUsul,
        public readonly int $sisaHari,
        public readonly string $url,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'routeNotificationForMail')
            ? ['database', 'mail']
            : ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengingat Deadline Usulan Kenaikan Pangkat')
            ->greeting('Yth. '.($notifiable->nama_lengkap ?? 'Pegawai').',')
            ->line($this->message())
            ->line('Harap segera melengkapi dan mengirim usulan kenaikan pangkat sebelum batas usul.')
            ->action('Lihat Usulan KP', $this->url)
            ->salutation('Hormat kami, Sistem Kepegawaian');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'usulan_id' => $this->usulanId,
            'notifiable_type' => $notifiable::class,
            'batas_usul' => $this->batasUsul->toDateString(),
            'sisa_hari' => $this->sisaHari,
            'url' => $this->url,
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        $tanggal = $this->batasUsul->translatedFormat('d F Y');

        if ($this->sisaHari <= 0) {
            return "Batas usul kenaikan pangkat telah jatuh tempo pada {$tanggal}.";
        }

        return "Batas usul kenaikan pangkat tersisa {$this->sisaHari} hari lagi ({$tanggal}).";
    }
}
