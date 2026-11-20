<?php

namespace App\Services;

use App\Models\Studio;
use App\Services\PriceList\Theme;
use App\Support\Color;
use App\Support\GdCanvas;
use App\Support\GdText;
use Illuminate\Support\Facades\Storage;

/**
 * Ảnh bảng giá (PNG) vẽ từ chính bảng giá đang công khai của studio, theo mẫu giao diện trang khách đang chọn
 * (Tối giản, Bàn vẽ, In Risograph, Lưới Thuỵ Sĩ, Sổ phác thảo, Tạp chí, Thẻ màu) và màu nhấn của studio.
 * Để tải về hoặc dán thẳng vào Zalo / Messenger / Facebook khi khách hỏi giá.
 *
 * Giống ShareImage: vẽ bằng GD ngay trên máy chủ (không cần Node, trình duyệt không đầu hay dịch vụ ngoài) và
 * dùng font nhúng sẵn trong resources/fonts nên chữ tiếng Việt đúng ở mọi máy chủ. Khác ShareImage: không lưu file —
 * mỗi lần bấm vẽ lại từ dữ liệu mới nhất, nên sửa giá xong bấm lại là có ảnh đúng.
 *
 * Phần vẽ (renderPayload) chỉ cần PHP + GD; việc lấy dữ liệu từ studio nằm ở payload().
 */
class PriceListImage
{
    /** Số dịch vụ tối đa trên 1 ảnh (ảnh dài quá khó gửi chat); phần còn lại ghi "và N dịch vụ khác". */
    public const MAX_SERVICES = 30;

    /** Bộ nhớ tối đa (byte) cho tờ vẽ ở độ phân giải cao; vượt thì giảm độ phân giải để không làm sập PHP. */
    private const CANVAS_BUDGET = 48 * 1024 * 1024;

    /** mẫu → lớp vẽ */
    private const THEMES = [
        'paper' => PriceList\PaperTheme::class,
        'artboard' => PriceList\ArtboardTheme::class,
        'riso' => PriceList\RisoTheme::class,
        'swiss' => PriceList\SwissTheme::class,
        'sketch' => PriceList\SketchTheme::class,
        'editorial' => PriceList\EditorialTheme::class,
        'swatch' => PriceList\SwatchTheme::class,
    ];

    /** khoá → [file, khoá dự phòng nếu thiếu file] */
    private const FONTS = [
        'body' => ['BeVietnamPro-Medium.ttf', null],
        'bodyb' => ['BeVietnamPro-Bold.ttf', 'body'],
        'serif' => ['Fraunces-Regular.ttf', 'body'],
        'serifb' => ['Fraunces-Bold.ttf', 'bodyb'],
        'serifi' => ['Fraunces-Italic.ttf', 'serif'],
        'hand' => ['PatrickHand-Regular.ttf', 'body'],
        'wide' => ['Unbounded-Bold.ttf', 'bodyb'],
        'swiss' => ['Archivo-Medium.ttf', 'body'],
        'swissb' => ['Archivo-ExtraBold.ttf', 'bodyb'],
        'grot' => ['SpaceGrotesk-Medium.ttf', 'body'],
        'grotb' => ['SpaceGrotesk-Bold.ttf', 'bodyb'],
        'mono' => ['JetBrainsMono-Medium.ttf', 'body'],
        'monob' => ['JetBrainsMono-Bold.ttf', 'bodyb'],
    ];

    public function __construct(private readonly ?string $fontDir = null) {}

    /** Máy chủ có vẽ được không (GD + FreeType + font nền). */
    public function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext') && $this->fonts() !== null;
    }

    /**
     * PNG bảng giá của studio theo mẫu $theme (mặc định mẫu studio đã chọn). Null khi không vẽ được
     * (thiếu GD / font, hoặc chưa có dịch vụ công khai nào).
     */
    public function render(Studio $studio, ?string $theme = null): ?string
    {
        $payload = $this->payload($studio);
        if ($payload['services'] === []) {
            return null;
        }

        return $this->renderPayload($payload, $theme ?? $studio->pageTheme());
    }

    /** Số dịch vụ công khai (= số dòng trên ảnh, chưa tính giới hạn). */
    public function count(Studio $studio): int
    {
        return $studio->services()->where('is_public', true)->count();
    }

    /**
     * Dữ liệu để vẽ — đúng những gì trang đặt đơn công khai đang hiện (dịch vụ ẩn không lên ảnh, không lộ số đơn nội bộ).
     *
     * @return array<string, mixed>
     */
    public function payload(Studio $studio): array
    {
        $all = $studio->services()->where('is_public', true)->orderBy('sort_order')->orderBy('id')->get();

        $services = [];
        foreach ($all->take(self::MAX_SERVICES) as $s) {
            $deposit = $s->effectiveDepositPercent($studio);
            $services[] = [
                'name' => (string) $s->name,
                'desc' => trim((string) $s->description),
                'price' => $s->priceLabel(),
                'known' => $s->price !== null,
                'meta' => ($s->duration_days ? '~'.$s->duration_days.' ngày làm · ' : '')
                    .$s->effectiveRevisionRounds($studio).' lần sửa · '
                    .($deposit > 0 ? 'cọc '.$deposit.'%' : 'không cần cọc'),
            ];
        }

        $avatar = null;
        if ($studio->avatar_path) {
            $disk = Storage::disk('public');
            if ($disk->exists($studio->avatar_path)) {
                $avatar = (string) $disk->get($studio->avatar_path);
            }
        }

        $host = (string) parse_url(url('/'), PHP_URL_HOST);
        $contact = array_values(array_filter([
            trim((string) $studio->phone),
            trim((string) $studio->email),
            $this->shortLink((string) $studio->contact_url),
        ], fn ($v) => $v !== ''));

        return [
            'name' => (string) $studio->name,
            'slug' => (string) $studio->slug,
            'headline' => trim((string) ($studio->page_headline ?: $studio->tagline)),
            'tagline' => trim((string) $studio->tagline),
            'accent' => $studio->accentHex(),
            'initials' => $studio->initials(),
            'avatar' => $avatar,
            'services' => $services,
            'more' => max(0, $all->count() - count($services)),
            'url' => $studio->order_form_enabled ? $host.'/dat/'.$studio->slug : null,
            'contact' => $contact,
            'updated' => 'Cập nhật '.now()->format('m/Y'),
            'month' => (int) now()->format('m'),
            'year' => (int) now()->format('Y'),
        ];
    }

    /**
     * Vẽ từ dữ liệu đã chuẩn bị (xem payload()). Null nếu máy chủ không vẽ được hoặc không có dòng nào.
     *
     * @param  array<string, mixed>  $d
     */
    public function renderPayload(array $d, string $theme): ?string
    {
        $fonts = $this->fonts();
        if ($fonts === null || ! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext') || ($d['services'] ?? []) === []) {
            return null;
        }

        $class = self::THEMES[$theme] ?? self::THEMES['paper'];
        /** @var Theme $style */
        $style = new $class;

        $d['accent'] = Color::normalize($d['accent'] ?? '') ?? '#c8106e';
        $d['pal'] = Color::palette($d['accent']);
        $d['contact'] ??= [];
        $d['url'] ??= null;
        $d['more'] ??= 0;

        $text = new GdText($fonts);
        $plan = $style->build($text, $d);
        $height = (int) ceil($plan['height']);

        $canvas = new GdCanvas(Theme::W, $height, $this->scaleFor(Theme::W, $height), $text);
        ($plan['paint'])($canvas);

        return $canvas->png();
    }

    /** Độ phân giải vẽ: 2× nếu vừa ngân sách bộ nhớ, ít hơn nếu ảnh dài; 1× khi PHP bị giới hạn bộ nhớ chặt. */
    private function scaleFor(int $w, int $h): float
    {
        $limit = $this->memoryLimit();
        if ($limit !== null && $limit < 96 * 1024 * 1024) {
            return 1.0;
        }
        foreach ([2.0, 1.5] as $scale) {
            if ($w * $scale * $h * $scale * 4 <= self::CANVAS_BUDGET) {
                return $scale;
            }
        }

        return 1.0;
    }

    /** memory_limit của PHP theo byte (null = không giới hạn). */
    private function memoryLimit(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return null;
        }
        $n = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /**
     * Danh sách font → đường dẫn file thật. Font mẫu nào thiếu file thì dùng Be Vietnam Pro thay (ảnh vẫn ra, chỉ kém đúng chất).
     * Null nếu thiếu cả font nền.
     *
     * @return array<string, string>|null
     */
    private function fonts(): ?array
    {
        $dir = rtrim($this->fontDir ?? resource_path('fonts'), '/\\');
        $found = [];
        foreach (self::FONTS as $key => [$file]) {
            if (is_file($dir.'/'.$file)) {
                $found[$key] = $dir.'/'.$file;
            }
        }
        if (! isset($found['body'], $found['bodyb'])) {
            return null;
        }

        $resolved = [];
        foreach (self::FONTS as $key => [, $fallback]) {
            $k = $key;
            while (! isset($found[$k]) && self::FONTS[$k][1] !== null) {
                $k = self::FONTS[$k][1];
            }
            $resolved[$key] = $found[$k];
        }

        return $resolved;
    }

    /** "https://zalo.me/0901234567?x=1" → "zalo.me/0901234567" (chỉ để in trên ảnh). */
    private function shortLink(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url(preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'https://'.$url);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $host = preg_replace('/^(www|m)\./i', '', $parts['host']);

        return rtrim($host.($parts['path'] ?? ''), '/');
    }
}
