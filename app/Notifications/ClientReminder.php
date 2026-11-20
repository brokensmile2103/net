<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Studio;
use App\Services\Mail\EmailContent;
use App\Services\Mail\StageContent;
use Illuminate\Support\Facades\URL;

/**
 * Email Nét tự gửi để nhắc khách (gửi thẳng tới email khách, không cần tài khoản):
 *  review  — phương án đang chờ khách xem & góp ý;
 *  quote   — báo giá chờ khách bấm đồng ý;
 *  payment — còn khoản cần thanh toán.
 * Người gửi hiển thị là tên studio, trả lời thư đi thẳng tới email của studio (nếu có). Cuối thư có link
 * "Không nhận email tự động cho đơn này". Giao diện theo mẫu trang đặt đơn của studio.
 */
class ClientReminder extends StudioNotification
{
    public function __construct(public Order $order, public string $kind, public bool $preview = false) {}

    public function studio(): Studio
    {
        return $this->order->studio;
    }

    public function subject(): string
    {
        $studio = $this->studio();
        $title = '“'.$this->order->title.'”';

        return match ($this->kind) {
            'review' => $studio->name.': phương án cho '.$title.' đang chờ bạn xem',
            'quote' => $studio->name.': báo giá cho '.$title.' đang chờ bạn xác nhận',
            default => $studio->name.': nhắc thanh toán đơn '.$this->order->code,
        };
    }

    public function content(): EmailContent
    {
        $order = $this->order;
        $studio = $this->studio();
        $title = '“'.$order->title.'”';
        $remain = max(0, $order->balance());

        [$heading, $paragraphs, $button] = match ($this->kind) {
            'review' => ['Phương án đang chờ bạn xem', [
                $studio->name.' đã gửi phương án thiết kế cho đơn **'.$order->code.'** '.$title.' và đang chờ bạn xem.',
                'Bạn có thể chọn phương án ưng ý, ghim góp ý thẳng lên hình, rồi bấm duyệt hoặc yêu cầu sửa — chỉ mất vài phút.',
            ], ['Xem phương án', $order->trackingUrl()]],
            'quote' => ['Báo giá đang chờ bạn xác nhận', [
                $studio->name.' đã gửi báo giá cho đơn **'.$order->code.'** '.$title.': tổng **'.money($order->total_amount).'đ**'
                    .($order->deposit_percent > 0 ? ', cọc '.$order->deposit_percent.'%' : '').'.',
                'Bạn xem chi tiết và bấm “Đồng ý báo giá” để studio bắt đầu làm. Có gì cần trao đổi, cứ nhắn ngay trên trang đơn.',
            ], ['Xem báo giá', $order->trackingUrl()]],
            default => ['Nhắc thanh toán', [
                'Đơn **'.$order->code.'** '.$title.' còn **'.money($remain).'đ** chưa thanh toán.'
                    .($order->nextPaymentAmount() > 0 ? ' Khoản cần trả tiếp: '.money($order->nextPaymentAmount()).'đ.' : ''),
                $order->lock_delivery && $order->exists && $order->sourceLinksQuery()->exists()
                    ? 'File bàn giao sẽ mở cho bạn ngay khi thanh toán đủ. Trang đơn có sẵn mã QR chuyển khoản đúng số tiền.'
                    : 'Trang đơn có sẵn mã QR chuyển khoản đúng số tiền — quét bằng app ngân hàng là xong.',
            ], ['Thanh toán', $order->trackingUrl().'#thanh-toan']],
        };

        $mute = $this->preview || ! $order->exists ? $order->trackingUrl() : URL::signedRoute('public.track.mute', ['token' => $order->tracking_token]);

        return new EmailContent(
            heading: $heading,
            paragraphs: $paragraphs,
            eyebrow: 'Đơn '.$order->code,
            preheader: strip_tags(str_replace('**', '', $paragraphs[0])),
            facts: StageContent::facts($order, $this->kind === 'payment'),
            steps: StageContent::steps($order),
            button: $button,
            after: $studio->phone ? ['Cần trao đổi nhanh: Zalo '.$studio->phone.'.'] : [],
            footer: 'Email này được gửi tự động vì bạn đặt thiết kế với '.$studio->name.'. Không muốn nhận email tự động cho đơn này? [Bấm vào đây]('.$mute.').',
            greeting: 'Chào '.($order->client?->name ?: 'bạn').',',
        );
    }
}
