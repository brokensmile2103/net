<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * 1 đơn thiết kế. total_amount / paid_amount là cache — luôn đồng bộ bằng syncTotals() / syncPaid().
 */
class Order extends Model
{
    /** studio_id, code, status, tracking_token, total/paid và các mốc thời gian cố tình KHÔNG fillable. */
    protected $fillable = [
        'client_id', 'service_id', 'title', 'priority', 'brief', 'style', 'colors', 'specs', 'copy_text',
        'budget_note', 'due_on', 'client_due_on', 'revision_rounds', 'deposit_percent', 'lock_delivery', 'internal_note',
    ];

    protected $hidden = ['tracking_token'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'client_due_on' => 'date',
            'revision_rounds' => 'integer',
            'deposit_percent' => 'integer',
            'lock_delivery' => 'boolean',
            'quote_sent_at' => 'datetime',
            'quote_accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'brief_answers' => 'array',
            'reminders_muted' => 'boolean',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /** Mỗi lần đổi giai đoạn (bất kể từ đâu: Bảng việc, khách duyệt, gửi vòng…) → ghi lại mốc để tính "đứng yên N ngày". */
    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            if (! $order->exists || $order->isDirty('status') || ! $order->status_changed_at) {
                $order->status_changed_at = now();
            }
        });
    }

    // ---- Quan hệ -----------------------------------------------------------

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function reminders()
    {
        return $this->hasMany(OrderReminder::class)->latest('sent_at');
    }

    public function charges()
    {
        return $this->hasMany(OrderCharge::class)->orderBy('sort_order')->orderBy('id');
    }

    public function links()
    {
        return $this->hasMany(OrderLink::class)->orderBy('id');
    }

    public function rounds()
    {
        return $this->hasMany(Round::class)->orderBy('number');
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class)->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    // ---- Phạm vi truy vấn ---------------------------------------------------

    /** Đơn còn đang chạy (chưa bàn giao, chưa huỷ). */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::openStatuses());
    }

    public static function openStatuses(): array
    {
        return array_keys(array_filter(config('net.statuses'), fn ($s) => $s['open']));
    }

    // ---- Trạng thái ---------------------------------------------------------

    public function meta(): array
    {
        return config('net.statuses.'.$this->status, config('net.statuses.new'));
    }

    public function statusLabel(): string
    {
        return $this->meta()['label'];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::openStatuses(), true);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /** Đã bàn giao VÀ thu đủ tiền. */
    public function isCompleted(): bool
    {
        return $this->isDelivered() && $this->balance() <= 0;
    }

    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on && $this->due_on->lt(Carbon::today());
    }

    /** Số ngày còn lại tới hạn (âm = đã trễ). null nếu chưa đặt hạn. */
    public function daysLeft(): ?int
    {
        return $this->due_on ? (int) Carbon::today()->diffInDays($this->due_on, false) : null;
    }

    public function dueLabel(): ?string
    {
        $d = $this->daysLeft();

        if ($d === null) {
            return null;
        }

        if (! $this->isOpen()) {
            return 'Hạn '.$this->due_on->format('d/m');
        }

        return match (true) {
            $d < 0 => 'Trễ '.abs($d).' ngày',
            $d === 0 => 'Hạn hôm nay',
            $d === 1 => 'Hạn ngày mai',
            default => 'Còn '.$d.' ngày',
        };
    }

    /** Câu trả lời mẫu brief (đã lưu kèm câu hỏi). @return list<array{question: string, answer: string}> */
    public function briefAnswers(): array
    {
        return collect(is_array($this->brief_answers) ? $this->brief_answers : [])
            ->filter(fn ($a) => is_array($a) && filled($a['question'] ?? null) && filled($a['answer'] ?? null))
            ->map(fn ($a) => ['question' => (string) $a['question'], 'answer' => is_array($a['answer']) ? implode(', ', $a['answer']) : (string) $a['answer']])
            ->values()->all();
    }

    /** Số ngày đơn nằm yên ở giai đoạn hiện tại. */
    public function daysInStatus(): int
    {
        return $this->status_changed_at ? (int) floor($this->status_changed_at->diffInDays(now(), true)) : 0;
    }

    /**
     * Đơn đang chạy mà nằm yên quá lâu ở 1 giai đoạn (mặc định 5 ngày, cấu hình net.board.stale_days).
     * "Chờ khách xem" dùng ngưỡng nhắc chờ khách để khớp với màn Hôm nay.
     */
    public function isStalled(): bool
    {
        return $this->isOpen() && $this->daysInStatus() >= self::staleDaysFor($this->status);
    }

    public static function staleDaysFor(string $status): int
    {
        return $status === 'review'
            ? max(1, (int) config('net.waiting_reminder_days', 3))
            : max(1, (int) config('net.board.stale_days', 5));
    }

    /** Vị trí trên thanh tiến độ khách thấy (0-based), -1 nếu đơn đã huỷ. */
    public function clientStepIndex(): int
    {
        if ($this->isCancelled()) {
            return -1;
        }

        foreach (config('net.client_steps') as $i => $step) {
            if (in_array($this->status, $step['statuses'], true)) {
                return $i;
            }
        }

        return 0;
    }

    // ---- Vòng sửa ----------------------------------------------------------

    /** Số lần khách đã yêu cầu sửa (mỗi vòng bị trả về "cần sửa" tính 1 lần). */
    public function revisionsUsed(): int
    {
        return $this->relationLoaded('rounds')
            ? $this->rounds->where('status', 'changes')->count()
            : $this->rounds()->where('status', 'changes')->count();
    }

    public function revisionsLeft(): int
    {
        return $this->revision_rounds - $this->revisionsUsed();
    }

    public function latestRound(): ?Round
    {
        return $this->relationLoaded('rounds') ? $this->rounds->sortByDesc('number')->first() : $this->rounds()->reorder('number', 'desc')->first();
    }

    // ---- Tiền --------------------------------------------------------------

    public function balance(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    /** Tiền cọc theo % — làm tròn lên tới nghìn đồng cho dễ chuyển khoản. */
    public function depositAmount(): float
    {
        if ((float) $this->total_amount <= 0 || $this->deposit_percent <= 0) {
            return 0.0;
        }

        return min((float) $this->total_amount, ceil((float) $this->total_amount * $this->deposit_percent / 100 / 1000) * 1000);
    }

    public function depositPaid(): bool
    {
        return (float) $this->paid_amount >= $this->depositAmount() - 0.01;
    }

    /** Đơn chưa được khách duyệt (còn đang báo giá / thiết kế / sửa). */
    public function beforeApproval(): bool
    {
        return in_array($this->status, ['new', 'quoted', 'in_progress', 'review', 'revising'], true);
    }

    /** Không yêu cầu cọc — quy trình đơn giản: nhận đơn → làm → duyệt → thanh toán 1 lần → bàn giao. */
    public function noDeposit(): bool
    {
        return (int) $this->deposit_percent <= 0;
    }

    /** Số tiền gợi ý khách chuyển tiếp theo: cọc (nếu chưa đủ cọc và chưa duyệt), ngược lại là phần còn lại. */
    public function nextPaymentAmount(): float
    {
        if ($this->balance() <= 0) {
            return 0.0;
        }

        if ($this->beforeApproval()) {
            // Đơn không cọc: khách trả một lần sau khi duyệt — chưa hiện mã chuyển khoản.
            if ($this->depositAmount() <= 0) {
                return 0.0;
            }
            if (! $this->depositPaid()) {
                return round($this->depositAmount() - (float) $this->paid_amount, 2);
            }
        }

        return $this->balance();
    }

    public function paymentStatus(): string
    {
        if ((float) $this->total_amount <= 0) {
            return 'none';
        }

        if ($this->balance() <= 0) {
            return 'paid';
        }

        if ((float) $this->paid_amount <= 0) {
            return 'unpaid';
        }

        return $this->depositAmount() > 0 && $this->depositPaid() ? 'deposit' : 'partial';
    }

    public function paymentLabel(): string
    {
        return [
            'none' => 'Chưa báo giá',
            'paid' => 'Đã thu đủ',
            'deposit' => 'Đã cọc',
            'partial' => 'Thu một phần',
            'unpaid' => 'Chưa thu',
        ][$this->paymentStatus()];
    }

    /** Khách có được xem link file bàn giao không. */
    public function deliveryUnlocked(): bool
    {
        return ! $this->lock_delivery || $this->balance() <= 0;
    }

    public function syncTotals(): void
    {
        $total = $this->charges()->get()->sum(fn (OrderCharge $c) => $c->signedAmount());
        $this->total_amount = max(0, round($total, 2));
        $this->save();
    }

    public function syncPaid(): void
    {
        $this->paid_amount = round((float) $this->payments()->sum('amount'), 2);
        $this->save();
    }

    /** Nội dung chuyển khoản gợi ý — ngắn, không dấu để mọi app ngân hàng nhận đúng. */
    public function transferMemo(): string
    {
        $template = $this->studio?->bankMemoTemplate() ?? Studio::DEFAULT_BANK_MEMO;

        return Studio::renderMemo($template, ['code' => $this->code]);
    }

    // ---- Khác ---------------------------------------------------------------

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function trackingUrl(): string
    {
        return route('public.track', $this->tracking_token);
    }

    public function sourceLinksQuery()
    {
        return $this->hasMany(OrderLink::class)->where('kind', 'source');
    }

    public function referenceLinks()
    {
        return $this->links->where('kind', 'reference');
    }

    public function sourceLinks()
    {
        return $this->links->where('kind', 'source');
    }
}
