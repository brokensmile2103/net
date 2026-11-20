<?php

namespace App\Services\Mail;

use App\Models\Order;

/**
 * Nội dung email báo tiến độ theo từng giai đoạn của đơn (config net.stage_emails.stages).
 * Lời nhắn riêng: lời nhắn chung của giai đoạn (Cài đặt) hoặc lời nhắn gõ khi bấm "Gửi email cho khách" ở trang đơn.
 */
class StageContent
{
    /** @return array{0: string, 1: EmailContent} [tiêu đề, nội dung] */
    public static function build(Order $order, string $stage, ?string $note = null, bool $preview = false): array
    {
        $studio = $order->studio;
        $title = '“'.$order->title.'”';
        $code = '**'.$order->code.'**';
        $url = $order->trackingUrl();
        $after = [];

        switch ($stage) {
            case 'new':
                $subject = $studio->name.' đã nhận yêu cầu '.$title;
                $heading = 'Đã nhận yêu cầu của bạn';
                $p = [
                    'Cảm ơn bạn đã gửi yêu cầu thiết kế '.$title.' (mã đơn '.$code.'). '.$studio->name.' sẽ đọc brief và phản hồi bạn sớm nhất.',
                    'Mọi thứ của đơn nằm ở một trang: tiến độ, báo giá, phương án, góp ý và file bàn giao. Lưu email này lại để mở nhanh nhé.',
                ];
                $button = ['Xem đơn của bạn', $url];
                break;
            case 'quoted':
                $subject = $studio->name.': báo giá cho '.$title;
                $heading = 'Báo giá đã sẵn sàng';
                $p = ['Báo giá cho đơn '.$code.' '.$title.': tổng **'.money($order->total_amount).'đ**'
                    .($order->depositAmount() > 0 ? ', đặt cọc '.$order->deposit_percent.'% (**'.money($order->depositAmount()).'đ**)' : '').'.',
                    'Bạn xem chi tiết từng khoản và bấm “Đồng ý báo giá” để bắt đầu. Cần điều chỉnh gì, cứ nhắn ngay trên trang đơn.'];
                $button = ['Xem báo giá', $url];
                break;
            case 'in_progress':
                $subject = $studio->name.' đã bắt đầu thiết kế '.$title;
                $heading = 'Bắt đầu thiết kế';
                $p = [$studio->name.' đã bắt đầu thiết kế '.$title.' (đơn '.$code.').'
                    .($order->due_on ? ' Dự kiến hoàn thành trước **'.$order->due_on->format('d/m/Y').'**.' : '')];
                $p[] = 'Khi có phương án, bạn sẽ nhận email và xem ngay trên trang đơn — chọn phương án, ghim góp ý lên hình, duyệt hoặc yêu cầu sửa.';
                $button = ['Theo dõi tiến độ', $url];
                break;
            case 'review':
                $round = $order->exists ? $order->rounds()->where('status', 'sent')->reorder('number', 'desc')->withCount('proposals')->first() : null;
                $label = $round ? 'V'.$round->number : 'mới';
                $subject = $studio->name.': phương án '.$label.' cho '.$title.' đã sẵn sàng';
                $heading = 'Có phương án mới cho bạn';
                $p = ['Phương án '.$label.' cho '.$title.' đã sẵn sàng'.($round && $round->proposals_count > 1 ? ' — **'.$round->proposals_count.' phương án** để bạn chọn' : '').'.',
                    'Bạn chọn phương án ưng ý, ghim góp ý thẳng lên hình rồi bấm duyệt hoặc yêu cầu sửa.'
                    .($order->revision_rounds > 0 ? ' Đơn có '.$order->revision_rounds.' lần sửa'.($order->exists ? ', còn '.$order->revisionsLeft() : '').'.' : '')];
                if ($round && filled($round->note)) {
                    $note = trim($note ? $note."\n\n".$round->note : $round->note);
                }
                $button = ['Xem phương án', $url];
                break;
            case 'revising':
                $subject = $studio->name.' đang chỉnh sửa '.$title.' theo góp ý';
                $heading = 'Đã nhận góp ý, đang chỉnh sửa';
                $p = ['Cảm ơn góp ý của bạn cho '.$title.'. '.$studio->name.' đang chỉnh sửa và sẽ gửi phương án mới sớm.'];
                $button = ['Xem lại góp ý', $url];
                break;
            case 'approved':
                $subject = $studio->name.': '.$title.' đã được duyệt';
                $heading = 'Thiết kế đã được duyệt';
                $p = ['Cảm ơn bạn đã duyệt thiết kế '.$title.'. '.$studio->name.' đang chuẩn bị file gốc để bàn giao.'];
                if ($order->nextPaymentAmount() > 0) {
                    $p[] = 'Khoản cần thanh toán tiếp: **'.money($order->nextPaymentAmount()).'đ** — trang đơn có sẵn mã QR đúng số tiền.';
                }
                $button = ['Xem đơn', $url];
                break;
            case 'delivered':
                $subject = $studio->name.' đã bàn giao file '.$title;
                $heading = 'File của bạn đã sẵn sàng';
                $locked = $order->lock_delivery && ! $order->deliveryUnlocked();
                $p = [$studio->name.' đã bàn giao file gốc cho đơn '.$code.' '.$title.'.',
                    $locked
                        ? 'Link tải sẽ mở ngay khi đơn được thanh toán đủ (còn **'.money(max(0, $order->balance())).'đ** — mã QR có sẵn trên trang đơn).'
                        : 'Bạn mở trang đơn để tải file. Nên tải về và lưu trữ riêng một bản nhé.'];
                $after[] = 'Cảm ơn bạn đã tin tưởng '.$studio->name.' — rất mong được làm cùng bạn lần sau!';
                $button = [$locked ? 'Thanh toán & nhận file' : 'Nhận file', $url.($locked ? '#thanh-toan' : '')];
                break;
            case 'cancelled':
                $subject = $studio->name.': đơn '.$order->code.' đã huỷ';
                $heading = 'Đơn đã được huỷ';
                $p = ['Đơn '.$code.' '.$title.' đã được huỷ'.($order->cancel_reason ? ': '.$order->cancel_reason : '').'.',
                    'Nếu có nhầm lẫn hoặc bạn muốn làm tiếp, chỉ cần trả lời email này.'];
                $button = ['Xem đơn', $url];
                break;
            default:
                $subject = $studio->name.': cập nhật đơn '.$order->code;
                $heading = 'Cập nhật đơn của bạn';
                $p = ['Đơn '.$code.' '.$title.' vừa được cập nhật: '.mb_strtolower($order->meta()['client_label'] ?? '').'.'];
                $button = ['Xem đơn', $url];
        }

        if ($studio->phone) {
            $after[] = 'Cần trao đổi nhanh: Zalo '.$studio->phone.'.';
        }
        $mute = $preview || ! $order->exists ? $url : \Illuminate\Support\Facades\URL::signedRoute('public.track.mute', ['token' => $order->tracking_token]);

        return [$subject, new EmailContent(
            heading: $heading,
            paragraphs: $p,
            eyebrow: 'Đơn '.$order->code.' · '.config('net.stage_emails.stages.'.$stage.'.label', 'Cập nhật'),
            preheader: str_replace('**', '', $p[0]),
            facts: self::facts($order, in_array($stage, ['quoted', 'approved', 'delivered'], true)),
            steps: $stage === 'cancelled' ? null : self::steps($order),
            button: $button,
            after: $after,
            note: filled($note) ? trim((string) $note) : null,
            footer: 'Bạn nhận email này vì đã đặt thiết kế với '.$studio->name.'. Không muốn nhận email tự động cho đơn này? [Bấm vào đây]('.$mute.').',
            greeting: 'Chào '.($order->client?->name ?: 'bạn').',',
        )];
    }

    /** @return list<array{0: string, 1: string}> */
    public static function facts(Order $order, bool $money = false): array
    {
        $rows = [['Mã đơn', $order->code], ['Dự án', $order->title]];
        if ($order->service?->name) {
            $rows[] = ['Dịch vụ', $order->service->name];
        }
        if ($money && (float) $order->total_amount > 0) {
            $rows[] = ['Tổng', money($order->total_amount).'đ'];
            if ((float) $order->paid_amount > 0) {
                $rows[] = ['Đã thanh toán', money($order->paid_amount).'đ'];
            }
        }

        return $rows;
    }

    /** @return array{labels: list<string>, current: int} */
    public static function steps(Order $order): array
    {
        return ['labels' => array_column(config('net.client_steps'), 'label'), 'current' => $order->status ? $order->clientStepIndex() : 0];
    }
}
