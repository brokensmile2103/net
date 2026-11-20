<?php

namespace App\Services\Mail;

use App\Models\Studio;
use App\Support\EmailTheme;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Dựng email gửi khách mang thương hiệu studio: người gửi = tên studio (địa chỉ gửi của Nét — tên miền đã xác thực ở
 * Resend), trả lời thư về email của studio, giao diện theo mẫu trang đặt đơn + màu nhấn.
 * Đồng thời điền greeting/line/action của MailMessage để bản chữ thuần và các công cụ đọc thư cũ vẫn đủ nội dung.
 */
class StudioMail
{
    public function __construct(private PlatformMail $platform) {}

    public function message(Studio $studio, string $subject, EmailContent $c, ?string $theme = null): MailMessage
    {
        [$address, $name] = $this->platform->fromFor($studio);
        $prefs = $studio->emailPrefs();

        $mail = (new MailMessage)->from($address, $name)->subject($subject);
        if ($prefs['reply_to']) {
            $mail->replyTo($prefs['reply_to'], $prefs['sender_name']);
        }
        if ($c->greeting) {
            $mail->greeting($c->greeting);
        }
        foreach ($c->paragraphs as $p) {
            $mail->line(EmailContent::plain($p));
        }
        if ($c->note) {
            $mail->line('Lời nhắn từ '.$studio->name.': '.$c->note);
        }
        if ($c->button) {
            $mail->action($c->button[0], $c->button[1]);
        }
        foreach ($c->after as $p) {
            $mail->line(EmailContent::plain($p));
        }
        $mail->salutation($c->signoff ?? ('Cảm ơn bạn,'."\n".$studio->name));
        if ($c->footer) {
            $mail->line('—')->line($c->footer);
        }
        foreach ($c->attachments as [$data, $file, $mime]) {
            $mail->attachData($data, $file, ['mime' => $mime]);
        }

        return $mail->view(['emails.studio', 'emails.studio-text'], $this->viewData($studio, $c, $subject, $theme));
    }

    /** Dữ liệu cho view — dùng chung cho gửi thật và trang "Xem trước". */
    public function viewData(Studio $studio, EmailContent $c, string $subject, ?string $theme = null): array
    {
        return [
            'studio' => $studio,
            't' => EmailTheme::for($studio, $theme),
            'c' => $c,
            'subject' => $subject,
            'signature' => $studio->emailPrefs()['signature'],
            'sandboxFor' => null,
        ];
    }

    /** HTML hoàn chỉnh (trang xem trước trong Cài đặt / trang đơn). */
    public function render(Studio $studio, string $subject, EmailContent $c, ?string $theme = null, ?string $sandboxFor = null): string
    {
        return view('emails.studio', ['sandboxFor' => $sandboxFor] + $this->viewData($studio, $c, $subject, $theme))->render();
    }
}
