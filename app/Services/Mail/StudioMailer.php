<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Studio;
use App\Notifications\StudioNotification;
use App\Services\ReminderService;
use App\Support\EmailCheck;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Cửa duy nhất để gửi email cho KHÁCH của 1 studio (nhắc khách, báo tiến độ, xác nhận hẹn). "Sandbox" từng studio,
 * giống cách mỗi studio có key Gemini riêng:
 *  - Hạn mức riêng mỗi ngày (mặc định net.mail.studio_daily, Super Admin đổi chung hoặc từng studio) + trần cả hệ thống.
 *  - Nhật ký riêng (email_logs): chỉ studio đó xem được; lỗi của studio này không chặn studio khác.
 *  - Chế độ thử: mọi email được chuyển về email của chính studio (gắn nhãn "Email thử"), khách không nhận gì.
 *  - Super Admin có thể tạm dừng email của 1 studio (VD bị báo spam).
 */
class StudioMailer
{
    public function __construct(private PlatformMail $platform) {}

    /** Số email đã gửi hôm nay (tính cả email ở chế độ thử). */
    public function usedToday(Studio $studio): int
    {
        return $studio->emailLogs()->whereIn('status', EmailLog::COUNTED)->where('sent_at', '>=', now()->startOfDay())->count();
    }

    public function platformUsedToday(): int
    {
        return EmailLog::query()->whereIn('status', EmailLog::COUNTED)->where('sent_at', '>=', now()->startOfDay())->count();
    }

    /** Lý do KHÔNG gửi được lúc này (null = gửi được). Không tính chuyện địa chỉ khách. */
    public function blocker(Studio $studio): ?string
    {
        if (! ReminderService::mailReady()) {
            return 'Máy chủ chưa cấu hình gửi email.';
        }
        if (! $studio->is_active) {
            return 'Studio đang tạm khoá.';
        }
        if ($studio->emailPrefs()['blocked']) {
            return 'Quản trị viên đang tạm dừng email của studio.';
        }
        $limit = $this->platform->studioLimit($studio);
        if ($limit <= 0 || $this->usedToday($studio) >= $limit) {
            return 'Đã hết hạn mức '.$limit.' email hôm nay — mai gửi tiếp.';
        }
        $cap = $this->platform->settings()['platform_daily'];
        if ($cap > 0 && $this->platformUsedToday() >= $cap) {
            return 'Hệ thống đã hết hạn mức email hôm nay.';
        }

        return null;
    }

    /**
     * Gửi ngay. Truyền $log (dòng "pending" có sẵn) để cập nhật thay vì tạo dòng mới.
     */
    public function deliver(Studio $studio, ?Order $order, string $kind, string $to, ?string $toName, StudioNotification $n, ?EmailLog $log = null, bool $requirePlausible = true): EmailLog
    {
        $to = trim($to);
        $log ??= new EmailLog(['studio_id' => $studio->id, 'order_id' => $order?->id, 'kind' => $kind, 'to_email' => $to]);
        $log->studio_id = $studio->id;
        $prefs = $studio->emailPrefs();

        $skip = function (string $why, string $status = 'skipped') use ($log) {
            $log->forceFill(['status' => $status, 'note' => Str::limit($why, 250, '…')])->save();

            return $log;
        };

        if ($why = $this->blocker($studio)) {
            return $skip($why);
        }
        // Chế độ thử: thư về hộp thư của studio nên chỉ cần đúng định dạng; gửi thật thì email phải "có vẻ thật".
        if ($prefs['sandbox'] ? ! filter_var($to, FILTER_VALIDATE_EMAIL) : ($requirePlausible && ! EmailCheck::plausible($to))) {
            return $skip('Email khách không hợp lệ hoặc không nhận được thư.');
        }

        $recipient = $to;
        $status = 'sent';
        if ($prefs['sandbox']) {
            $own = trim((string) $studio->email);
            if (! filter_var($own, FILTER_VALIDATE_EMAIL)) {
                return $skip('Chế độ thử đang bật nhưng studio chưa có email để nhận thư thử.');
            }
            $n->sandboxFor = $to;
            $recipient = $own;
            $toName = $studio->name;
            $status = 'sandbox';
        }

        try {
            Notification::route('mail', [$recipient => $toName ?: $recipient])->notifyNow($n);
        } catch (\Throwable $e) {
            Log::warning('Nét: studio '.$studio->id.' không gửi được email '.$kind.' — '.$e->getMessage());

            return $skip(self::friendlyError($e->getMessage()), 'failed');
        }

        $log->forceFill([
            'status' => $status,
            'subject' => Str::limit((string) $n->subjectLine(), 250, '…'),
            'note' => $status === 'sandbox' ? 'Chế độ thử: đã chuyển tới '.$recipient : $log->note,
            'sent_at' => now(),
        ])->save();

        return $log;
    }

    private static function friendlyError(string $msg): string
    {
        return match (true) {
            str_contains($msg, 'domain is not verified') || str_contains($msg, 'verify a domain') => 'Tên miền gửi chưa xác thực ở Resend (báo quản trị viên).',
            str_contains($msg, 'API key') || str_contains($msg, 'api_key') => 'API key gửi email không hợp lệ (báo quản trị viên).',
            str_contains($msg, 'rate') || str_contains($msg, '429') => 'Máy chủ gửi thư đang quá tải — thử lại sau.',
            default => 'Lỗi gửi: '.Str::limit($msg, 180),
        };
    }

    /** Dọn nhật ký quá cũ (gọi thỉnh thoảng). */
    public function prune(): void
    {
        EmailLog::query()->where('created_at', '<', now()->subDays((int) config('net.mail.keep_days', 180)))->delete();
    }
}
