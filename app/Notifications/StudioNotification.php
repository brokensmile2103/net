<?php

namespace App\Notifications;

use App\Models\Studio;
use App\Services\Mail\EmailContent;
use App\Services\Mail\StudioMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email gửi KHÁCH thay mặt 1 studio — luôn gửi qua App\Services\Mail\StudioMailer (hạn mức, nhật ký, chế độ thử).
 * Lớp con chỉ cần trả về studio, tiêu đề và nội dung; giao diện do StudioMail dựng theo mẫu trang đặt đơn.
 */
abstract class StudioNotification extends Notification
{
    /** Chế độ thử: email thật lẽ ra gửi tới địa chỉ này (hiện nhãn "Email thử" trong thư). */
    public ?string $sandboxFor = null;

    /** Ép mẫu giao diện (xem trước mẫu khác); null = mẫu studio đang chọn. */
    public ?string $theme = null;

    abstract public function studio(): Studio;

    abstract public function subject(): string;

    abstract public function content(): EmailContent;

    public function subjectLine(): string
    {
        return ($this->sandboxFor ? '[Thử] ' : '').$this->subject();
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = app(StudioMail::class)->message($this->studio(), $this->subjectLine(), $this->content(), $this->theme);
        $mail->viewData['sandboxFor'] = $this->sandboxFor;

        return $mail;
    }

    /** HTML để xem trước trong trình duyệt. */
    public function preview(): string
    {
        return app(StudioMail::class)->render($this->studio(), $this->subjectLine(), $this->content(), $this->theme, $this->sandboxFor);
    }
}
