<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 1 email Nét gửi (hoặc sắp gửi) cho khách của 1 studio: hàng chờ (pending) + nhật ký. Dùng để đếm hạn mức
 * riêng của từng studio, để chủ studio xem lại đã gửi gì, và để huỷ email giai đoạn khi đơn đổi ý giữa chừng.
 */
class EmailLog extends Model
{
    public const COUNTED = ['sent', 'sandbox'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['send_at' => 'datetime', 'sent_at' => 'datetime', 'payload' => 'array'];
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function kindLabel(): string
    {
        [$group, $key] = array_pad(explode(':', $this->kind, 2), 2, '');

        return match ($group) {
            'stage' => 'Tiến độ: '.config('net.stage_emails.stages.'.$key.'.label', $key),
            'reminder' => 'Nhắc: '.config('net.reminders.kinds.'.$key.'.label', $key),
            'consult' => 'Xác nhận hẹn tư vấn',
            'test' => 'Email thử',
            default => $this->kind,
        };
    }

    public function statusLabel(): array
    {
        return match ($this->status) {
            'sent' => ['Đã gửi', 'bg-emerald-50 text-emerald-700'],
            'sandbox' => ['Chế độ thử', 'bg-sky-50 text-sky-700'],
            'pending' => ['Chờ gửi', 'bg-amber-50 text-amber-800'],
            'failed' => ['Lỗi', 'bg-red-50 text-red-700'],
            'cancelled' => ['Đã huỷ', 'bg-stone-100 text-stone-600'],
            default => ['Bỏ qua', 'bg-stone-100 text-stone-600'],
        };
    }
}
