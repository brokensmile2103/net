<?php

namespace App\Notifications;

use App\Models\Consultation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email xác nhận cuộc hẹn gọi tư vấn: gửi khách (for = client) và báo studio (for = studio). Kèm file .ics. */
class ConsultationBooked extends Notification
{
    public function __construct(public Consultation $c, public string $for = 'client') {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $c = $this->c;
        $studio = $c->studio;
        $mail = (new MailMessage)->from((string) config('mail.from.address'), $this->for === 'client' ? $studio->name : 'Nét');

        if ($this->for === 'studio') {
            return $mail->subject('Lịch hẹn tư vấn mới: '.$c->name.' · '.$c->whenLabel())
                ->greeting('Có lịch hẹn mới!')
                ->line($c->name.' vừa đặt lịch gọi tư vấn: '.$c->whenLabel().' ('.$c->channelLabel().').')
                ->line('Liên hệ: '.collect([$c->phone, $c->email, $c->facebook])->filter()->implode(' · '))
                ->when($c->note, fn ($m) => $m->line('Khách muốn trao đổi: '.$c->note))
                ->action('Xem lịch hẹn', route('consultations.index'))
                ->attachData($c->ics('Gọi tư vấn: '.$c->name, $c->channelLabel()."\n".collect([$c->phone, $c->email])->filter()->implode(' · ')), 'lich-hen.ics', ['mime' => 'text/calendar']);
        }

        $note = $studio->consultPrefs()['note'];
        if ($studio->email) {
            $mail->replyTo($studio->email, $studio->name);
        }

        return $mail->subject('Đã đặt lịch gọi tư vấn với '.$studio->name.' · '.$c->whenLabel())
            ->greeting('Chào '.$c->name.',')
            ->line('Bạn đã đặt lịch gọi tư vấn với '.$studio->name.': **'.$c->whenLabel().'** — '.$c->channelLabel().'.')
            ->when($note !== '', fn ($m) => $m->line($note))
            ->line('Đổi ý hoặc bận đột xuất? Bạn có thể huỷ hẹn ngay trên trang lịch hẹn.')
            ->action('Xem lịch hẹn', $c->publicUrl())
            ->salutation('Hẹn gặp bạn,'."\n".$studio->name)
            ->attachData($c->ics('Gọi tư vấn với '.$studio->name, $c->channelLabel()."\n".$c->publicUrl()), 'lich-hen.ics', ['mime' => 'text/calendar']);
    }
}
