<?php

namespace App\Services\Analytics;

use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Đếm lượt xem trang đặt đơn — gọn, riêng tư, không cần cookie hay dịch vụ ngoài:
 *  - Mỗi người xem = dấu vân tay sha256(APP_KEY + studio + ngày + IP + trình duyệt), cắt 16 ký tự. Đổi mỗi ngày nên
 *    không lần ra được IP hay theo dấu người xem qua nhiều ngày; "người xem" = số người khác nhau trong từng ngày.
 *  - Nguồn: ?utm_source / ?nguon trước, rồi trình duyệt trong app (Facebook, Messenger, Zalo, Instagram, TikTok…
 *    — các app này thường KHÔNG gửi Referer), rồi Referer.
 *  - Bỏ qua: chủ studio đang đăng nhập, bot / trình xem trước link (facebookexternalhit, Zalo…), prefetch.
 * Nguồn của lượt xem đầu tiên trong phiên được nhớ lại để gắn vào đơn / lịch hẹn khách gửi sau đó.
 */
class PageAnalytics
{
    public const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|zalo(?:bot|preview)|preview|embedly|whatsapp|telegram|discord|skype|curl|wget|python|okhttp|go-http|headless|lighthouse|pagespeed|monitor|uptime/i';

    public function record(Request $request, Studio $studio): void
    {
        try {
            if (! $this->countable($request, $studio)) {
                return;
            }
            $source = $this->source($request);
            $this->rememberSource($request, $studio, $source);

            $day = Carbon::today()->toDateString();
            $visitor = substr(hash('sha256', config('app.key').'|'.$studio->id.'|'.$day.'|'.$request->ip().'|'.$request->userAgent()), 0, 16);

            $inserted = DB::table('page_visits')->insertOrIgnore([
                'studio_id' => $studio->id, 'day' => $day, 'visitor' => $visitor,
                'source' => $source, 'device' => $this->device((string) $request->userAgent()), 'views' => 1,
            ]);
            if (! $inserted) {
                DB::table('page_visits')->where(['studio_id' => $studio->id, 'day' => $day, 'visitor' => $visitor])->increment('views');
            }

            // Dọn dữ liệu quá cũ thỉnh thoảng (1/200 lượt) — không cần cron.
            if (random_int(1, 200) === 1) {
                DB::table('page_visits')->where('day', '<', Carbon::today()->subDays((int) config('net.analytics.keep_days', 400))->toDateString())->delete();
            }
        } catch (\Throwable $e) {
            // Thống kê không bao giờ được làm hỏng trang đặt đơn.
            Log::debug('Nét: không ghi được lượt xem — '.$e->getMessage());
        }
    }

    public function countable(Request $request, Studio $studio): bool
    {
        $ua = (string) $request->userAgent();
        if ($ua === '' || preg_match(self::BOT_PATTERN, $ua) && ! $this->inAppSource($ua)) {
            return false;
        }
        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? $request->header('X-Moz') ?? ''));
        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return false;
        }

        // Chủ studio tự mở trang (xem thử, kiểm tra) không tính.
        return $request->user()?->id !== $studio->owner_id;
    }

    /** Nguồn đã chuẩn hoá (khoá trong config net.analytics.sources). */
    public function source(Request $request): string
    {
        $utm = strtolower(trim((string) ($request->query('utm_source') ?? $request->query('nguon') ?? $request->query('ref') ?? '')));
        if ($utm !== '' && ($s = $this->fromName($utm))) {
            return $s;
        }
        if ($utm !== '') {
            return 'other';
        }

        if ($s = $this->inAppSource((string) $request->userAgent())) {
            return $s;
        }

        $ref = (string) $request->headers->get('referer', '');
        $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
        if ($host === '' || $host === strtolower($request->getHost())) {
            return 'direct';
        }

        return $this->fromHost($host) ?? 'other';
    }

    public function inAppSource(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/FBAN|FBAV|FB_IAB|FBIOS|FB4A|Messenger|MESSENGER/', $ua) => 'facebook',
            (bool) preg_match('/Zalo/i', $ua) => 'zalo',
            (bool) preg_match('/Instagram/i', $ua) => 'instagram',
            (bool) preg_match('/musical_ly|TikTok|BytedanceWebview|trill/i', $ua) => 'tiktok',
            (bool) preg_match('/Barcelona/', $ua) => 'threads', // app Threads
            (bool) preg_match('/LinkedInApp/i', $ua) => 'linkedin',
            (bool) preg_match('/Pinterest/i', $ua) => 'pinterest',
            default => null,
        };
    }

    public function fromName(string $name): ?string
    {
        $name = preg_replace('/[^a-z0-9]/', '', $name);

        return match (true) {
            in_array($name, ['fb', 'facebook', 'messenger', 'mess', 'meta', 'fanpage', 'group', 'nhom'], true) => 'facebook',
            in_array($name, ['zalo', 'zl', 'zalooa'], true) => 'zalo',
            in_array($name, ['ig', 'insta', 'instagram'], true) => 'instagram',
            in_array($name, ['tiktok', 'tt'], true) => 'tiktok',
            in_array($name, ['threads'], true) => 'threads',
            in_array($name, ['x', 'twitter'], true) => 'x',
            in_array($name, ['google', 'gg', 'search', 'bing', 'coccoc'], true) => 'google',
            in_array($name, ['behance', 'be', 'dribbble'], true) => 'behance',
            in_array($name, ['pinterest', 'pin'], true) => 'pinterest',
            in_array($name, ['linkedin', 'li'], true) => 'linkedin',
            in_array($name, ['email', 'mail', 'newsletter', 'gmail'], true) => 'email',
            default => null,
        };
    }

    public function fromHost(string $host): ?string
    {
        $host = preg_replace('/^(www|m|l|lm|web|mbasic|touch)\./', '', $host);
        $is = fn (string ...$domains) => collect($domains)->contains(fn ($d) => $host === $d || str_ends_with($host, '.'.$d));

        return match (true) {
            $is('facebook.com', 'fb.com', 'fb.me', 'messenger.com', 'm.me') => 'facebook',
            $is('zalo.me', 'zalo.vn', 'zaloapp.com') => 'zalo',
            $is('instagram.com') => 'instagram',
            $is('tiktok.com') => 'tiktok',
            $is('threads.net', 'threads.com') => 'threads',
            $is('x.com', 'twitter.com', 't.co') => 'x',
            $is('mail.google.com', 'outlook.live.com', 'mail.yahoo.com') => 'email',
            (bool) preg_match('/(^|\.)google\.[a-z.]+$/', $host), $is('bing.com', 'coccoc.com', 'duckduckgo.com', 'yahoo.com') => 'google',
            $is('behance.net', 'dribbble.com') => 'behance',
            $is('pinterest.com', 'pin.it') || (bool) preg_match('/(^|\.)pinterest\.[a-z.]+$/', $host) => 'pinterest',
            $is('linkedin.com', 'lnkd.in') => 'linkedin',
            default => null,
        };
    }

    public function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|Opera Mini/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    private function rememberSource(Request $request, Studio $studio, string $source): void
    {
        if (! $request->hasSession()) {
            return;
        }
        $key = 'net.ref.'.$studio->id;
        // Giữ nguồn đầu tiên của phiên; chỉ thay khi lần trước là "không rõ" mà lần này biết rõ hơn.
        $prev = $request->session()->get($key);
        if (! $prev || ($prev === 'direct' && $source !== 'direct')) {
            $request->session()->put($key, $source);
        }
    }

    /** Nguồn đã nhớ trong phiên (gắn vào đơn / lịch hẹn khách vừa gửi). */
    public static function sessionSource(Request $request, Studio $studio): ?string
    {
        $s = $request->hasSession() ? $request->session()->get('net.ref.'.$studio->id) : null;

        return is_string($s) && array_key_exists($s, config('net.analytics.sources')) ? $s : null;
    }
}
