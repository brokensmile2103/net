<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Studio;
use App\Notifications\ClientReminder;
use App\Support\EmailCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Tự động nhắc khách qua email — không cần cron.
 *
 * Chạy kèm lượt truy cập web (middleware RunDueReminders, sau khi trả trang cho người dùng), tối đa 1 lần mỗi
 * `net.reminders.every_minutes` phút, mỗi lượt tối đa `net.reminders.batch` email. Có cron thì có thể chạy
 * `php artisan net:nhac-khach` (đã có sẵn trong lịch, mỗi giờ).
 *
 * Mỗi đơn, mỗi loại nhắc: chỉ gửi khi đủ N ngày kể từ mốc (vào "Chờ khách xem", gửi báo giá, duyệt/bàn giao) và
 * N ngày kể từ lần nhắc trước, tối đa `max` lần cho mỗi mốc — mốc mới (VD: gửi vòng V2) thì đếm lại từ đầu.
 * Không gửi khi: studio tắt nhắc, khách bấm "không nhận email nhắc", email không "có vẻ thật", ngoài khung giờ.
 */
class ReminderService
{
    public function __construct(private OrderService $orders) {}

    /** Gọi sau mỗi lượt truy cập web: tự giới hạn tần suất + khoá chống chạy trùng. */
    public function runIfDue(): void
    {
        if (! config('net.reminders.auto_run', true)) {
            return;
        }
        $every = max(1, (int) config('net.reminders.every_minutes', 15));
        if (! Cache::add('net:reminders:ran', now()->timestamp, now()->addMinutes($every))) {
            return;
        }
        try {
            $this->run();
        } catch (\Throwable $e) {
            Log::warning('Nét: nhắc khách tự động lỗi — '.$e->getMessage());
        }
    }

    /** @return int số email đã gửi */
    public function run(?int $limit = null): int
    {
        if (! self::mailReady() || ! $this->withinHours()) {
            return 0;
        }
        $lock = Cache::lock('net:reminders:lock', 300);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $limit ??= (int) config('net.reminders.batch', 20);
            $sent = 0;
            $studios = Studio::query()->where('is_active', true)->whereNotNull('reminder_prefs')->get()
                ->filter(fn (Studio $s) => $s->reminderPrefs()['enabled']);
            foreach ($studios as $studio) {
                foreach ($this->due($studio) as [$order, $kind]) {
                    if ($sent >= $limit) {
                        return $sent;
                    }
                    if ($this->send($order, $kind)) {
                        $sent++;
                    }
                }
            }

            return $sent;
        } finally {
            $lock->release();
        }
    }

    /**
     * Các đơn của studio đến lượt nhắc (mỗi đơn tối đa 1 email mỗi lượt, ưu tiên: phương án → báo giá → thanh toán).
     *
     * @return list<array{0: Order, 1: string}>
     */
    public function due(Studio $studio): array
    {
        $prefs = $studio->reminderPrefs();
        if (! $prefs['enabled']) {
            return [];
        }
        $orders = $studio->orders()->with(['client', 'reminders', 'studio'])
            ->where('reminders_muted', false)
            ->whereIn('status', ['quoted', 'review', 'approved', 'delivered'])
            ->whereHas('client', fn ($c) => $c->whereNotNull('email')->where('email', '!=', ''))
            ->get();

        $out = [];
        foreach ($orders as $order) {
            if (! EmailCheck::plausible($order->client->email)) {
                continue;
            }
            foreach (['review', 'quote', 'payment'] as $kind) {
                $rule = $prefs['kinds'][$kind];
                $anchor = $this->anchor($order, $kind);
                if (! $rule['on'] || ! $anchor || $anchor->gt(now()->subDays($rule['days']))) {
                    continue;
                }
                $previous = $order->reminders->where('kind', $kind)->filter(fn ($r) => $r->sent_at->gte($anchor));
                if ($previous->count() >= $prefs['max']) {
                    continue;
                }
                $last = $previous->max('sent_at');
                if ($last && Carbon::parse($last)->gt(now()->subDays($rule['days']))) {
                    continue;
                }
                $out[] = [$order, $kind];
                break;
            }
        }

        return $out;
    }

    /** Mốc tính ngày của từng loại nhắc; null = đơn không thuộc loại này. */
    public function anchor(Order $order, string $kind): ?Carbon
    {
        return match ($kind) {
            'review' => $order->status === 'review' ? ($order->status_changed_at ?? $order->updated_at) : null,
            'quote' => $order->status === 'quoted' && $order->quote_sent_at && ! $order->quote_accepted_at && (float) $order->total_amount > 0 ? $order->quote_sent_at : null,
            'payment' => in_array($order->status, ['approved', 'delivered'], true) && $order->balance() > 0 && $order->nextPaymentAmount() > 0
                ? ($order->delivered_at ?? $order->status_changed_at ?? $order->updated_at) : null,
            default => null,
        };
    }

    public function send(Order $order, string $kind): bool
    {
        $email = trim((string) $order->client?->email);
        try {
            Notification::route('mail', [$email => $order->client->name])->notifyNow(new ClientReminder($order, $kind));
        } catch (\Throwable $e) {
            Log::warning('Nét: không gửi được email nhắc '.$order->code.' — '.$e->getMessage());

            return false;
        }
        $order->reminders()->create(['kind' => $kind, 'email' => $email, 'sent_at' => now()]);
        $this->orders->log($order, 'note', 'Nét đã tự gửi email nhắc khách ('.mb_strtolower(config('net.reminders.kinds.'.$kind.'.label')).') tới '.$email.'.', 'system', false);

        return true;
    }

    public function withinHours(): bool
    {
        [$from, $to] = array_pad((array) config('net.reminders.hours', [8, 20]), 2, 24);
        $h = (int) now()->format('G');

        return $h >= (int) $from && $h < (int) $to;
    }

    /** Máy chủ đã cấu hình gửi email thật chưa (không phải log / array / Resend thiếu key). */
    public static function mailReady(): bool
    {
        $mailer = (string) config('mail.default');

        return match ($mailer) {
            'log', 'array', '' => false,
            'resend' => filled(config('services.resend.key')),
            default => filled(config('mail.from.address')),
        };
    }
}
