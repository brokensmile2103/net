<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Ghi đè nội dung mail mặc định của Laravel (tiếng Anh) bằng bản tiếng Việt,
 * đúng giọng văn Nét. Kế thừa ResetPasswordBase thay vì viết lại từ đầu
 * để giữ nguyên toàn bộ cơ chế token/hết hạn đã được Laravel xử lý đúng.
 */
class ResetPasswordNotification extends ResetPasswordBase
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu Nét')
            ->greeting('Xin chào'.($notifiable->name ? ', '.$notifiable->name : '').'!')
            ->line('Có người vừa yêu cầu đặt lại mật khẩu cho tài khoản Nét gắn với số điện thoại '.$notifiable->phone.'.')
            ->action('Đặt lại mật khẩu', $url)
            ->line("Link này sẽ hết hạn sau {$expireMinutes} phút kể từ lúc yêu cầu.")
            ->line('Nếu bạn không yêu cầu điều này, có thể bỏ qua email — mật khẩu hiện tại của bạn vẫn an toàn, không ai đổi được nếu không bấm vào link trên.')
            ->salutation('Trân trọng,'."\n".'Nét');
    }
}
