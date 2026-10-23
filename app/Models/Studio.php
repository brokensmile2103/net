<?php

namespace App\Models;

use App\Services\ShareImage;
use App\Support\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Studio của 1 freelancer = 1 tenant. Mọi dữ liệu nghiệp vụ gắn studio_id. */
class Studio extends Model
{
    /** owner_id, is_active, order_seq, storage_bytes cố tình KHÔNG fillable. */
    protected $fillable = [
        'name', 'slug', 'tagline', 'bio', 'phone', 'email', 'contact_url', 'accent_color',
        'page_theme', 'accent_custom', 'page_headline',
        'order_form_enabled', 'order_form_note', 'policy',
        'default_deposit_percent', 'default_revision_rounds', 'lock_delivery_until_paid',
        'bank_code', 'bank_account', 'bank_holder', 'bank_memo',
    ];

    /** API key Gemini không bao giờ được lộ ra JSON / xuất dữ liệu. */
    protected $hidden = ['gemini_api_key'];

    protected function casts(): array
    {
        return [
            'gemini_api_key' => 'encrypted',
            'ai_models' => 'array',
            'is_active' => 'boolean',
            'order_form_enabled' => 'boolean',
            'lock_delivery_until_paid' => 'boolean',
            'default_deposit_percent' => 'integer',
            'default_revision_rounds' => 'integer',
            'storage_bytes' => 'integer',
            'board_prefs' => 'array',
            'reminder_prefs' => 'array',
            'consult_prefs' => 'array',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function images()
    {
        return $this->hasMany(ProposalImage::class);
    }

    /**
     * API key Gemini đã giải mã, null nếu chưa có. Không ném lỗi khi APP_KEY đổi (giá trị cũ không giải mã được nữa)
     * — coi như chưa có key, chủ studio dán lại là xong, thay vì cả trang lỗi 500.
     */
    public function geminiKey(): ?string
    {
        try {
            $key = $this->gemini_api_key;
        } catch (\Throwable) {
            return null;
        }

        return filled($key) ? (string) $key : null;
    }

    /** Mô hình AI theo thứ tự thử: studio tự chọn, chưa chọn thì theo mặc định hệ thống. */
    public function aiModelIds(): array
    {
        $own = array_values(array_filter((array) ($this->ai_models ?? []), fn ($id) => is_string($id) && $id !== ''));

        return $own ?: array_values((array) config('net.ai.default_models'));
    }

    public function usesDefaultAiModels(): bool
    {
        return empty(array_filter((array) ($this->ai_models ?? [])));
    }

    public function hasAi(): bool
    {
        return $this->geminiKey() !== null;
    }

    /** "AIza••••••••3f9K" — để chủ studio nhận ra key nào đang dùng. */
    public function maskedGeminiKey(): ?string
    {
        $key = $this->geminiKey();

        return $key ? substr($key, 0, 4).str_repeat('•', 8).substr($key, -4) : null;
    }

    public const DEFAULT_BANK_MEMO = 'NET {code}';

    /** Mẫu nội dung chuyển khoản điền sẵn vào VietQR ({code} = mã đơn). Rỗng = không điền sẵn. */
    public function bankMemoTemplate(): string
    {
        return array_key_exists('bank_memo', $this->attributes)
            ? (string) $this->attributes['bank_memo']
            : self::DEFAULT_BANK_MEMO;
    }

    /** Làm sạch mẫu khi lưu: bỏ dấu, chỉ giữ chữ, số, khoảng trắng, gạch ngang và {biến}. */
    public static function cleanMemoTemplate(string $template): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 \-{}]/', '', \Illuminate\Support\Str::ascii($template))));
    }

    /** Thay biến, bỏ dấu và ký tự lạ (nhiều app ngân hàng từ chối nội dung có dấu/ký tự đặc biệt). */
    public static function renderMemo(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        $template = preg_replace('/\{[^}]*\}/', '', $template);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 \-]/', '', \Illuminate\Support\Str::ascii($template))));
    }

    public function hasVietQr(): bool
    {
        return filled($this->bank_code) && filled($this->bank_account);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: 'N';
    }

    public function accentHex(): string
    {
        if ($this->accent_color === 'custom' && ($custom = Color::normalize($this->accent_custom))) {
            return $custom;
        }

        return config('net.accent_colors.'.$this->accent_color.'.hex', '#c8106e');
    }

    /**
     * Biến CSS màu nhấn đặt vào <html style="…">. Màu có sẵn đã có bộ màu viết tay trong app.css nên chỉ cần thêm
     * mực Riso; màu tự chọn thì sinh cả bộ (rê chuột, chữ, nền nhạt, chữ trên nền nhấn) từ mã HEX.
     */
    public function accentStyle(): string
    {
        $palette = Color::palette($this->accentHex());

        return Color::styleAttr($this->accent_color === 'custom'
            ? $palette
            : array_intersect_key($palette, array_flip(['--ink-riso', '--on-riso', '--riso-face', '--riso-shadow'])));
    }

    public function pageTheme(): string
    {
        return array_key_exists((string) $this->page_theme, config('net.page_themes')) ? $this->page_theme : 'paper';
    }

    /**
     * Tuỳ chọn Bảng việc đã chuẩn hoá (giá trị lạ trong DB bị bỏ qua, thiếu thì lấy mặc định).
     *
     * @return array{view: string, sort: string, hidden: list<string>, collapsed: list<string>, limits: array<string, int>, delivered_days: int}
     */
    public function boardPrefs(): array
    {
        $raw = is_array($this->board_prefs) ? $this->board_prefs : [];
        $columns = array_keys(array_filter(config('net.statuses'), fn ($s) => $s['board']));
        $only = fn ($list) => array_values(array_intersect($columns, array_map('strval', (array) $list)));

        $limits = [];
        foreach ((array) ($raw['limits'] ?? []) as $status => $n) {
            if (in_array($status, $columns, true) && (int) $n > 0) {
                $limits[$status] = min(999, (int) $n);
            }
        }
        $days = (int) ($raw['delivered_days'] ?? 30);

        return [
            'view' => array_key_exists($raw['view'] ?? '', config('net.board.views')) ? $raw['view'] : 'cards',
            'sort' => array_key_exists($raw['sort'] ?? '', config('net.board.sorts')) ? $raw['sort'] : 'priority',
            'hidden' => $only($raw['hidden'] ?? []),
            'collapsed' => $only($raw['collapsed'] ?? []),
            'limits' => $limits,
            'delivered_days' => in_array($days, config('net.board.delivered_days'), true) ? $days : 30,
        ];
    }

    /**
     * Cài đặt nhắc khách tự động (mặc định TẮT). Mỗi loại nhắc: bật/tắt, sau bao nhiêu ngày, nhắc tối đa mấy lần
     * (các lần cách nhau đúng số ngày đó).
     *
     * @return array{enabled: bool, kinds: array<string, array{on: bool, days: int}>, max: int}
     */
    public function reminderPrefs(): array
    {
        $raw = is_array($this->reminder_prefs) ? $this->reminder_prefs : [];
        $kinds = [];
        foreach (array_keys(config('net.reminders.kinds')) as $k) {
            $r = (array) ($raw['kinds'][$k] ?? []);
            $kinds[$k] = ['on' => (bool) ($r['on'] ?? true), 'days' => max(1, min(30, (int) ($r['days'] ?? 3)))];
        }

        return ['enabled' => (bool) ($raw['enabled'] ?? false), 'kinds' => $kinds, 'max' => max(1, min(5, (int) ($raw['max'] ?? 2)))];
    }

    /**
     * Cài đặt hẹn gọi tư vấn (mặc định TẮT): thời lượng mỗi cuộc, các ngày trong tuần + khung giờ nhận hẹn,
     * phải đặt trước ít nhất bao nhiêu giờ, cho đặt xa nhất bao nhiêu ngày, kênh gọi, lời nhắn cho khách.
     *
     * @return array{enabled: bool, duration: int, days: list<int>, ranges: list<array{0: string, 1: string}>, notice_hours: int, ahead_days: int, channels: list<string>, note: string}
     */
    public function consultPrefs(): array
    {
        $raw = is_array($this->consult_prefs) ? $this->consult_prefs : [];
        $durations = config('net.consult.durations');
        $time = fn ($t) => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : null;
        $ranges = [];
        foreach ((array) ($raw['ranges'] ?? [['09:00', '11:30'], ['14:00', '17:00']]) as $r) {
            [$a, $b] = [$time($r[0] ?? null), $time($r[1] ?? null)];
            if ($a && $b && $a < $b) {
                $ranges[] = [$a, $b];
            }
        }
        $channels = array_values(array_intersect(array_keys(config('net.consult.channels')), (array) ($raw['channels'] ?? ['zalo', 'phone'])));
        $days = array_values(array_intersect([1, 2, 3, 4, 5, 6, 7], array_map('intval', (array) ($raw['days'] ?? [1, 2, 3, 4, 5]))));

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'duration' => in_array((int) ($raw['duration'] ?? 30), $durations, true) ? (int) ($raw['duration'] ?? 30) : 30,
            'days' => $days,
            'ranges' => array_slice($ranges, 0, (int) config('net.consult.max_ranges', 3)),
            'notice_hours' => max(0, min(168, (int) ($raw['notice_hours'] ?? 12))),
            'ahead_days' => max(1, min(60, (int) ($raw['ahead_days'] ?? 14))),
            'channels' => $channels ?: ['zalo'],
            'note' => mb_substr(trim((string) ($raw['note'] ?? '')), 0, 500),
        ];
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function consultEnabled(): bool
    {
        $p = $this->consultPrefs();

        return $p['enabled'] && $p['days'] && $p['ranges'];
    }

    public function portfolioItems()
    {
        return $this->hasMany(PortfolioItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function orderFormUrl(): string
    {
        return route('public.order', $this->slug);
    }

    /** Link ảnh xem trước khi chia sẻ (og:image) — 1200×630 JPEG, ?v= đổi theo nội dung để mạng xã hội lấy ảnh mới. */
    public function shareImageUrl(): string
    {
        return route('public.share-image', ['slug' => $this->slug, 'v' => app(ShareImage::class)->fingerprint($this)]);
    }

    /**
     * Mô tả ngắn khi chia sẻ link trang đặt đơn (og:description / meta description):
     * câu chào hoặc khẩu hiệu, giới thiệu, rồi các dịch vụ đang nhận. Tối đa ~200 ký tự, không xuống dòng.
     *
     * @param  iterable<Service>|null  $services  dịch vụ công khai (đã tải sẵn ở trang đặt đơn)
     */
    public function shareDescription(?iterable $services = null): string
    {
        $clean = fn (?string $t) => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $t)));
        $parts = array_values(array_unique(array_filter([$clean($this->page_headline), $clean($this->tagline)])));

        $names = collect($services ?? [])->pluck('name')->map($clean)->filter()->take(4);
        if ($names->isNotEmpty()) {
            $parts[] = 'Nhận: '.$names->implode(', ');
        }
        if (count($parts) < 2 && ($bio = $clean($this->bio)) !== '') {
            $parts[] = $bio;
        }
        if (! $parts) {
            $parts[] = 'Đặt thiết kế với '.$this->name.': gửi brief, xem phương án, góp ý và nhận file — tất cả trên một đường link.';
        }

        $text = implode(' · ', $parts);

        return mb_strlen($text) > 200 ? rtrim(mb_substr($text, 0, 197), ' ,.;:·–—-').'…' : $text;
    }

    public function storageQuotaBytes(): int
    {
        return max(1, (int) config('net.storage_quota_mb', 500)) * 1024 * 1024;
    }

    public function storageUsedPercent(): int
    {
        return (int) min(100, round($this->storage_bytes / $this->storageQuotaBytes() * 100));
    }

    /** Thư mục lưu ảnh của studio trên disk "public". */
    public function storageDir(string $sub = ''): string
    {
        return 'studios/'.$this->id.($sub !== '' ? '/'.$sub : '');
    }
}
