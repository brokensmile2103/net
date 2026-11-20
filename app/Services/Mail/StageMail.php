<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use App\Models\Order;
use App\Notifications\StageUpdate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Email báo tiến độ theo giai đoạn — qua HÀNG CHỜ (email_logs, status = pending):
 *  - Đơn đổi giai đoạn → xếp 1 email, gửi sau net.stage_emails.delay_minutes phút. Trong lúc chờ mà đơn lại đổi
 *    giai đoạn (kéo nhầm thẻ, Hoàn tác…) thì email cũ bị huỷ, chỉ email của giai đoạn mới được xếp.
 *  - "Đã nhận yêu cầu" (đơn khách tự đặt) và email freelancer bấm gửi tay: gửi ngay.
 *  - Không cần cron: hàng chờ được xử lý sau mỗi lượt truy cập web (middleware RunDueReminders) hoặc
 *    `php artisan net:gui-email` (đã có trong lịch, mỗi phút).
 */
class StageMail
{
    public function __construct(private StudioMailer $mailer) {}

    /** Có nên tự gửi email cho giai đoạn hiện tại của đơn không (theo cài đặt studio). */
    public function autoEnabled(Order $order, string $stage): bool
    {
        $prefs = $order->studio?->emailPrefs();
        if (! $prefs || ! $prefs['stages_on'] || ! ($prefs['stages'][$stage]['on'] ?? false)) {
            return false;
        }

        return match ($stage) {
            'new' => $order->source === 'form',
            'quoted' => (float) $order->total_amount > 0 && $order->quote_sent_at !== null,
            default => array_key_exists($stage, config('net.stage_emails.stages')),
        };
    }

    /**
     * Xếp email cho giai đoạn hiện tại của đơn. $manual = freelancer bấm gửi (bỏ qua công tắc tự động & chống trùng).
     * Trả về dòng hàng chờ, hoặc null nếu không cần gửi.
     */
    public function queue(Order $order, ?string $note = null, bool $manual = false): ?EmailLog
    {
        $stage = (string) $order->status;
        $order->loadMissing('studio', 'client');
        if (! $order->studio || ! array_key_exists($stage, config('net.stage_emails.stages'))) {
            return null;
        }
        if (! $manual && (! $this->autoEnabled($order, $stage) || $order->reminders_muted)) {
            // Đơn rời giai đoạn cũ → email cũ đang chờ (nếu có) không còn đúng nữa.
            $this->cancelPending($order, 'Đơn đã chuyển sang giai đoạn khác trước khi gửi.');

            return null;
        }
        $email = trim((string) $order->client?->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $kind = 'stage:'.$stage;
        $this->cancelPending($order, 'Đơn đã chuyển sang giai đoạn khác trước khi gửi.', $manual ? null : $kind);

        // Chống trùng: cùng giai đoạn vừa xếp / vừa gửi trong 30 phút (VD đơn được lưu nhiều lần liền nhau).
        if (! $manual && $order->emailLogs()->where('kind', $kind)->whereIn('status', ['pending', 'sent', 'sandbox'])
            ->where('created_at', '>=', now()->subMinutes(30))->exists()) {
            return null;
        }

        $delay = ($manual || $stage === 'new') ? 0 : max(0, (int) config('net.stage_emails.delay_minutes', 3));
        $stageNote = $order->studio->emailPrefs()['stages'][$stage]['note'] ?? '';

        return $order->emailLogs()->create([
            'studio_id' => $order->studio_id,
            'kind' => $kind,
            'to_email' => $email,
            'status' => 'pending',
            'payload' => array_filter(['note' => $manual ? $note : ($stageNote ?: null), 'manual' => $manual ?: null]),
            'send_at' => now()->addMinutes($delay),
        ]);
    }

    /** Huỷ email giai đoạn đang chờ của đơn (trừ $keepKind). */
    public function cancelPending(Order $order, string $why, ?string $keepKind = null): void
    {
        $order->emailLogs()->where('status', 'pending')->where('kind', 'like', 'stage:%')
            ->when($keepKind, fn ($q) => $q->where('kind', '!=', $keepKind))
            ->update(['status' => 'cancelled', 'note' => $why, 'updated_at' => now()]);
    }

    /** Gửi các email đã tới giờ. @return int số email đã đi (kể cả chế độ thử) */
    public function processDue(?int $limit = null): int
    {
        if (! EmailLog::query()->where('status', 'pending')->where('send_at', '<=', now())->exists()) {
            return 0;
        }
        $lock = Cache::lock('net:outbox:lock', 120);
        if (! $lock->get()) {
            return 0;
        }
        try {
            $sent = 0;
            $logs = EmailLog::query()->where('status', 'pending')->where('send_at', '<=', now())
                ->orderBy('send_at')->limit($limit ?? (int) config('net.mail.batch', 15))->get();
            foreach ($logs as $log) {
                if (in_array($this->send($log)->status, EmailLog::COUNTED, true)) {
                    $sent++;
                }
            }
            if (random_int(1, 50) === 1) {
                $this->mailer->prune();
            }

            return $sent;
        } finally {
            $lock->release();
        }
    }

    /** Gửi kèm lượt truy cập web — nhẹ: chỉ 1 truy vấn khi hàng chờ trống. */
    public function runIfDue(): void
    {
        if (! config('net.mail.auto_run', true)) {
            return;
        }
        try {
            $this->processDue();
        } catch (\Throwable $e) {
            Log::warning('Nét: xử lý hàng chờ email lỗi — '.$e->getMessage());
        }
    }

    public function send(EmailLog $log): EmailLog
    {
        $order = $log->order()->with(['studio', 'client', 'service'])->first();
        $stage = str_starts_with($log->kind, 'stage:') ? substr($log->kind, 6) : null;
        $manual = (bool) ($log->payload['manual'] ?? false);
        if (! $order || ! $stage || ! $order->studio) {
            $log->forceFill(['status' => 'cancelled', 'note' => 'Đơn không còn tồn tại.'])->save();

            return $log;
        }
        if (! $manual && $order->status !== $stage) {
            $log->forceFill(['status' => 'cancelled', 'note' => 'Đơn đã chuyển sang giai đoạn khác trước khi gửi.'])->save();

            return $log;
        }
        if (! $manual && $order->reminders_muted) {
            $log->forceFill(['status' => 'cancelled', 'note' => 'Khách đã tắt email tự động cho đơn này.'])->save();

            return $log;
        }

        $log = $this->mailer->deliver($order->studio, $order, $log->kind, $log->to_email, $order->client?->name,
            new StageUpdate($order, $stage, $log->payload['note'] ?? null), $log);

        if (in_array($log->status, EmailLog::COUNTED, true)) {
            app(\App\Services\OrderService::class)->log($order, 'note', ($log->status === 'sandbox' ? '[Chế độ thử] ' : '').'Đã gửi email “'
                .config('net.stage_emails.stages.'.$stage.'.label').'” tới '.$log->to_email.'.', 'system', false);
        }

        return $log;
    }
}
