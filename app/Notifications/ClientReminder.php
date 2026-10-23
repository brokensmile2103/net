<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Email Nét tự gửi để nhắc khách (gửi thẳng tới email khách, không cần tài khoản):
 *  review  — phương án đang chờ khách xem & góp ý;
 *  quote   — báo giá chờ khách bấm đồng ý;
 *  payment — còn khoản cần thanh toán.
 * Người gửi hiển thị là tên studio, trả lời thư đi thẳng tới email của studio (nếu có). Cuối thư có link
 * "Không nhận email nhắc cho đơn này".
 */
class ClientReminder extends Notification
{
    public function __construct(public Order $order, public string $kind, public bool $preview = false) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->order;
        $studio = $order->studio;
        $name = $order->client?->name;
        $title = '“'.$order->title.'”';

        $mail = (new MailMessage)
            ->from((string) config('mail.from.address'), $studio->name)
            ->greeting('Chào '.($name ?: 'bạn').',');
        if ($studio->email) {
            $mail->replyTo($studio->email, $studio->name);
        }

        match ($this->kind) {
            'review' => $mail->subject($studio->name.': phương án cho '.$title.' đang chờ bạn xem')
                ->line($studio->name.' đã gửi phương án thiết kế cho đơn '.$order->code.' '.$title.' và đang chờ bạn xem.')
                ->line('Bạn có thể chọn phương án ưng ý, ghim góp ý thẳng lên hình, rồi bấm duyệt hoặc yêu cầu sửa — chỉ mất vài phút.')
                ->action('Xem phương án', $order->trackingUrl()),
            'quote' => $mail->subject($studio->name.': báo giá cho '.$title.' đang chờ bạn xác nhận')
                ->line($studio->name.' đã gửi báo giá cho đơn '.$order->code.' '.$title.': tổng '.money($order->total_amount).'đ'
                    .($order->deposit_percent > 0 ? ', cọc '.$order->deposit_percent.'%' : '').'.')
                ->line('Bạn xem chi tiết và bấm “Đồng ý báo giá” để studio bắt đầu làm. Có gì cần trao đổi, cứ nhắn ngay trên trang đơn.')
                ->action('Xem báo giá', $order->trackingUrl()),
            default => $mail->subject($studio->name.': nhắc thanh toán đơn '.$order->code)
                ->line('Đơn '.$order->code.' '.$title.' còn '.money(max(0, $order->balance())).'đ chưa thanh toán.'
                    .($order->nextPaymentAmount() > 0 ? ' Khoản cần trả tiếp: '.money($order->nextPaymentAmount()).'đ.' : ''))
                ->line($order->lock_delivery && $order->sourceLinksQuery()->exists()
                    ? 'File bàn giao sẽ mở cho bạn ngay khi thanh toán đủ. Trang đơn có sẵn mã QR chuyển khoản đúng số tiền.'
                    : 'Trang đơn có sẵn mã QR chuyển khoản đúng số tiền — quét bằng app ngân hàng là xong.')
                ->action('Thanh toán', $order->trackingUrl().'#thanh-toan'),
        };

        if ($studio->phone) {
            $mail->line('Cần trao đổi nhanh: Zalo '.$studio->phone.'.');
        }

        $mute = $this->preview ? $order->trackingUrl() : URL::signedRoute('public.track.mute', ['token' => $order->tracking_token]);

        return $mail->salutation('Cảm ơn bạn,'."\n".$studio->name)
            ->line('—')
            ->line('Email này được gửi tự động vì bạn đặt thiết kế với '.$studio->name.'. Không muốn nhận email nhắc cho đơn này? [Bấm vào đây]('.$mute.').');
    }
}
