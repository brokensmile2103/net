<?php

namespace App\Notifications;

use App\Models\Consultation;
use App\Models\Studio;
use App\Services\Mail\EmailContent;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email xác nhận cuộc hẹn gọi tư vấn: gửi khách (for = client — giao diện theo mẫu của studio, qua StudioMailer)
 * và báo studio (for = studio — email hệ thống của Nét). Cả hai kèm file .ics.
 */
class ConsultationBooked extends StudioNotification
{
    public function __construct(public Consultation $c, public string $for = 'client') {}

    public function studio(): Studio
    {
        return $this->c->studio;
    }

    public function subject(): string
    {
        return 'Đã đặt lịch gọi tư vấn với '.$this->c->studio->name.' · '.$this->c->whenLabel();
    }

    public function content(): EmailContent
    {
        $c = $this->c;
        $studio = $c->studio;
        $note = $studio->consultPrefs()['note'];

        return new EmailContent(
            heading: 'Hẹn gặp bạn '.$c->starts_at->format('H:i').' · '.$c->starts_at->format('d/m'),
            paragraphs: [
                'Bạn đã đặt lịch gọi tư vấn với '.$studio->name.': **'.$c->whenLabel().'** — '.$c->channelLabel().'.',
                'Đổi ý hoặc bận đột xuất? Bạn có thể huỷ hẹn ngay trên trang lịch hẹn.',
            ],
            eyebrow: 'Lịch hẹn tư vấn',
            preheader: $c->whenLabel().' · '.$c->channelLabel(),
            facts: array_values(array_filter([['Thời gian', $c->whenLabel()], ['Thời lượng', $c->duration.' phút'], ['Cách gọi', $c->channelLabel()], $c->note ? ['Bạn muốn trao đổi', $c->note] : null])),
            button: ['Xem lịch hẹn', $c->publicUrl()],
            after: ['File lịch (.ics) đính kèm — mở để thêm vào lịch điện thoại, có nhắc trước 15 phút.'],
            note: $note !== '' ? $note : null,
            greeting: 'Chào '.$c->name.',',
            signoff: 'Hẹn gặp bạn,'."\n".$studio->name,
            attachments: [[$c->ics('Gọi tư vấn với '.$studio->name, $c->channelLabel()."\n".$c->publicUrl()), 'lich-hen.ics', 'text/calendar']],
        );
    }

    public function toMail($notifiable): MailMessage
    {
        if ($this->for !== 'studio') {
            return parent::toMail($notifiable);
        }

        $c = $this->c;

        return (new MailMessage)->from((string) config('mail.from.address'), 'Nét')
            ->subject('Lịch hẹn tư vấn mới: '.$c->name.' · '.$c->whenLabel())
            ->greeting('Có lịch hẹn mới!')
            ->line($c->name.' vừa đặt lịch gọi tư vấn: '.$c->whenLabel().' ('.$c->channelLabel().').')
            ->line('Liên hệ: '.collect([$c->phone, $c->email, $c->facebook])->filter()->implode(' · '))
            ->when($c->note, fn ($m) => $m->line('Khách muốn trao đổi: '.$c->note))
            ->action('Xem lịch hẹn', route('consultations.index'))
            ->attachData($c->ics('Gọi tư vấn: '.$c->name, $c->channelLabel()."\n".collect([$c->phone, $c->email])->filter()->implode(' · ')), 'lich-hen.ics', ['mime' => 'text/calendar']);
    }
}
