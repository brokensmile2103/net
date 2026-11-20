<?php

namespace App\Services\Mail;

use App\Models\PlatformSetting;
use App\Models\Studio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Máy chủ gửi thư DÙNG CHUNG của Nét (Super Admin cấu hình 1 lần ở /sieu-quan-tri/cai-dat).
 *
 * Resend bắt buộc tên miền người gửi đã xác thực (onboarding@resend.dev chỉ gửi được tới chính chủ tài khoản) — freelancer
 * hiếm ai có tên miền riêng, nên mọi studio gửi qua API key + tên miền của Nét: người gửi hiển thị là TÊN STUDIO, trả lời
 * thư về email của studio. Có cấu hình ở đây thì ghi đè MAIL_MAILER/RESEND_API_KEY trong .env; không có thì dùng .env.
 * API key lưu mã hoá (APP_KEY) trong platform_settings.
 */
class PlatformMail
{
    private const CACHE = 'net:platform-mail:v1';

    /**
     * @return array{resend_key: ?string, from_address: ?string, from_name: ?string, per_studio_address: bool, studio_daily: int, platform_daily: int}
     */
    public function settings(): array
    {
        $raw = Cache::remember(self::CACHE, 600, fn () => [
            'resend_key' => PlatformSetting::get('mail_resend_key'),
            'from_address' => PlatformSetting::get('mail_from_address'),
            'from_name' => PlatformSetting::get('mail_from_name'),
            'per_studio_address' => PlatformSetting::get('mail_per_studio_address', '0'),
            'studio_daily' => PlatformSetting::get('mail_studio_daily'),
            'platform_daily' => PlatformSetting::get('mail_platform_daily'),
        ]);

        $key = null;
        if (filled($raw['resend_key'] ?? null)) {
            try {
                $key = Crypt::decryptString($raw['resend_key']);
            } catch (\Throwable) {
                $key = null; // APP_KEY đổi → coi như chưa có key, Super Admin dán lại.
            }
        }

        return [
            'resend_key' => $key ?: null,
            'from_address' => filled($raw['from_address'] ?? null) ? (string) $raw['from_address'] : null,
            'from_name' => filled($raw['from_name'] ?? null) ? (string) $raw['from_name'] : null,
            'per_studio_address' => ($raw['per_studio_address'] ?? '0') === '1',
            'studio_daily' => is_numeric($raw['studio_daily'] ?? null) ? max(0, (int) $raw['studio_daily']) : (int) config('net.mail.studio_daily', 50),
            'platform_daily' => is_numeric($raw['platform_daily'] ?? null) ? max(0, (int) $raw['platform_daily']) : (int) config('net.mail.platform_daily', 0),
        ];
    }

    /** Ghi cấu hình Resend của Nét vào config mail cho request hiện tại (gọi ở AppServiceProvider::boot). */
    public function apply(): void
    {
        try {
            $s = $this->settings();
        } catch (\Throwable) {
            return; // chưa cài đặt / chưa có database
        }
        if ($s['resend_key']) {
            config(['mail.default' => 'resend', 'services.resend.key' => $s['resend_key']]);
        }
        if ($s['from_address']) {
            config(['mail.from.address' => $s['from_address']]);
        }
        if ($s['from_name']) {
            config(['mail.from.name' => $s['from_name']]);
        }
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    public function save(array $values): void
    {
        foreach ($values as $k => $v) {
            if ($k === 'mail_resend_key' && filled($v)) {
                $v = Crypt::encryptString((string) $v);
            }
            PlatformSetting::put($k, $v === null ? null : (string) $v);
        }
        Cache::forget(self::CACHE);
        $this->apply();
    }

    /** Nguồn cấu hình đang dùng: platform (Super Admin), env (.env), none. */
    public function source(): string
    {
        if ($this->settings()['resend_key']) {
            return 'platform';
        }

        return \App\Services\ReminderService::mailReady() ? 'env' : 'none';
    }

    public static function domainOf(?string $address): ?string
    {
        $address = trim((string) $address);

        return str_contains($address, '@') ? strtolower(substr(strrchr($address, '@'), 1)) : null;
    }

    /**
     * Địa chỉ + tên người gửi cho 1 studio. Tên = tên người gửi studio tự đặt (mặc định tên studio). Địa chỉ = địa chỉ
     * gửi của Nét; bật "địa chỉ riêng từng studio" thì thành {slug}@tên-miền (cùng tên miền đã xác thực nên Resend vẫn nhận).
     *
     * @return array{0: string, 1: string}
     */
    public function fromFor(Studio $studio): array
    {
        $s = $this->settings();
        $address = (string) ($s['from_address'] ?: config('mail.from.address'));
        if ($s['per_studio_address'] && ($domain = self::domainOf($address)) && preg_match('/^[a-z0-9-]{3,40}$/', (string) $studio->slug)) {
            $address = $studio->slug.'@'.$domain;
        }
        // Bỏ ký tự có thể phá header (", <, >, xuống dòng).
        $name = trim(preg_replace('/[\r\n"<>]+/u', ' ', $studio->emailPrefs()['sender_name']));

        return [$address, $name !== '' ? $name : (string) config('mail.from.name', 'Nét')];
    }

    public function studioLimit(Studio $studio): int
    {
        return $studio->emailPrefs()['limit'] ?? $this->settings()['studio_daily'];
    }

    /**
     * Kiểm tra key + tên miền người gửi với Resend (GET /domains).
     *
     * @return array{ok: bool, level: string, message: string}
     */
    public function verify(string $key, string $from): array
    {
        $domain = self::domainOf($from);
        if (! $domain) {
            return ['ok' => false, 'level' => 'error', 'message' => 'Địa chỉ gửi không hợp lệ.'];
        }
        if (in_array($domain, ['resend.dev', 'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com'], true)) {
            return ['ok' => false, 'level' => 'error', 'message' => 'Không gửi được từ @'.$domain.' — Resend chỉ cho gửi tới khách từ tên miền của bạn đã xác thực (resend.com/domains).'];
        }

        try {
            $res = Http::withToken($key)->acceptJson()->timeout(12)->get('https://api.resend.com/domains');
        } catch (\Throwable $e) {
            return ['ok' => true, 'level' => 'warn', 'message' => 'Chưa kết nối được Resend để kiểm tra ('.$e->getMessage().') — đã lưu, hãy bấm “Gửi email thử”.'];
        }

        if ($res->status() === 401 || in_array($res->json('name'), ['invalid_api_key', 'missing_api_key'], true)) {
            return ['ok' => false, 'level' => 'error', 'message' => 'API key không đúng hoặc đã bị xoá.'];
        }
        if ($res->status() === 403 || $res->json('name') === 'restricted_api_key') {
            return ['ok' => true, 'level' => 'warn', 'message' => 'Key chỉ có quyền gửi email (Sending access) nên Nét không kiểm tra được tên miền — hãy bấm “Gửi email thử” để chắc chắn.'];
        }
        if (! $res->successful()) {
            return ['ok' => true, 'level' => 'warn', 'message' => 'Resend trả lỗi '.$res->status().' khi kiểm tra — đã lưu, hãy bấm “Gửi email thử”.'];
        }

        $found = collect((array) $res->json('data'))->first(fn ($d) => strtolower((string) ($d['name'] ?? '')) === $domain);
        if (! $found) {
            $names = collect((array) $res->json('data'))->pluck('name')->filter()->implode(', ');

            return ['ok' => false, 'level' => 'error', 'message' => 'Tên miền '.$domain.' chưa có trong tài khoản Resend'.($names ? ' (đang có: '.$names.')' : '').'. Thêm & xác thực ở resend.com/domains, hoặc đổi địa chỉ gửi.'];
        }
        if (($found['status'] ?? '') !== 'verified') {
            return ['ok' => false, 'level' => 'error', 'message' => 'Tên miền '.$domain.' chưa xác thực xong (trạng thái: '.($found['status'] ?? '?').'). Thêm bản ghi DNS Resend hướng dẫn rồi lưu lại.'];
        }

        return ['ok' => true, 'level' => 'ok', 'message' => 'Đã kết nối Resend — tên miền '.$domain.' đã xác thực.'];
    }
}
