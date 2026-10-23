<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Lọc email "có vẻ thật" trước khi Nét tự gửi mail nhắc khách.
 *
 * Không có cách nào chắc chắn 100% một hộp thư tồn tại mà không gửi thử, nên Nét loại các trường hợp
 * gần như chắc chắn là giả hoặc gửi sẽ hỏng — tránh mail bị trả về (làm xấu uy tín tên miền gửi) và tránh làm phiền nhầm người:
 *  1. Sai định dạng / tên miền không hợp lệ.
 *  2. Gõ nhầm tên miền phổ biến (gmial.com, gmail.con, yahooo.com…) → kèm gợi ý sửa.
 *  3. Email mẫu, email thử: @example.com, test@…, abc@…, aaaa@…, 123@…
 *  4. Email dùng một lần (mailinator, 10minutemail, yopmail, temp-mail…).
 *  5. Gmail có phần tên dưới 6 ký tự (Gmail không cho tạo tên ngắn như vậy).
 *  6. Tên miền không nhận được thư (không có bản ghi MX/A) — kiểm tra DNS, nhớ kết quả 1 ngày; tên miền phổ biến bỏ qua bước này.
 */
class EmailCheck
{
    /** Tên miền email phổ biến — bỏ qua kiểm tra DNS. */
    private const COMMON = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.com.vn', 'ymail.com', 'outlook.com', 'outlook.com.vn', 'hotmail.com',
        'live.com', 'msn.com', 'icloud.com', 'me.com', 'mac.com', 'proton.me', 'protonmail.com', 'zoho.com', 'aol.com', 'gmx.com', 'yandex.com',
    ];

    /** Gõ nhầm hay gặp → tên miền đúng. */
    private const TYPOS = [
        'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'gmail.om' => 'gmail.com', 'gmail.vn' => 'gmail.com',
        'gmail.com.vn' => 'gmail.com', 'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmaill.com' => 'gmail.com',
        'gnail.com' => 'gmail.com', 'gmali.com' => 'gmail.com', 'gmeil.com' => 'gmail.com', 'gmail.comm' => 'gmail.com', 'gmal.com' => 'gmail.com',
        'gmil.com' => 'gmail.com', 'gmail.cpm' => 'gmail.com', 'gmail.xom' => 'gmail.com', 'gmaul.com' => 'gmail.com',
        'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com', 'yahoo.co' => 'yahoo.com', 'yahoo.con' => 'yahoo.com', 'yhoo.com' => 'yahoo.com',
        'yahoo.vn' => 'yahoo.com.vn', 'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com', 'hotmail.co' => 'hotmail.com', 'hotmal.com' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outloo.com' => 'outlook.com', 'outlook.co' => 'outlook.com', 'icloud.co' => 'icloud.com', 'iclod.com' => 'icloud.com',
    ];

    /** Tên miền mẫu / thử. */
    private const PLACEHOLDER_DOMAINS = [
        'example.com', 'example.net', 'example.org', 'example.vn', 'test.com', 'test.vn', 'test.net', 'testing.com', 'domain.com', 'yourdomain.com',
        'abc.com', 'abc.vn', 'xyz.com', 'a.com', 'aa.com', 'aaa.com', 'b.com', 'asd.com', 'asdf.com', 'qwe.com', 'email.test', 'localhost', 'invalid',
    ];

    /** Phần tên hay dùng khi điền bừa. */
    private const PLACEHOLDER_LOCALS = [
        'test', 'testing', 'tester', 'abc', 'abcd', 'abcdef', 'asd', 'asdf', 'asdasd', 'qwe', 'qwerty', 'xxx', 'xyz', 'none', 'null', 'no', 'na', 'n/a',
        'noemail', 'nomail', 'khongco', 'khong', 'chuaco', 'noreply', 'no-reply', 'donotreply', 'fake', 'sample',
    ];

    /** Dịch vụ email dùng một lần (rút gọn — các dịch vụ phổ biến nhất). */
    private const DISPOSABLE = [
        'mailinator.com', 'maildrop.cc', '10minutemail.com', '10minutemail.net', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamailblock.com',
        'sharklasers.com', 'grr.la', 'yopmail.com', 'yopmail.net', 'yopmail.fr', 'temp-mail.org', 'tempmail.com', 'tempmail.net', 'tempmailo.com',
        'tempr.email', 'temp-mail.io', 'throwawaymail.com', 'trashmail.com', 'trashmail.de', 'getnada.com', 'nada.email', 'dispostable.com',
        'mailnesia.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com', 'fakeinbox.com', 'fakemail.net', 'spambox.us', 'mytemp.email',
        'burnermail.io', 'moakt.com', 'tmail.ws', 'tmpmail.org', 'tmpmail.net', 'linshiyouxiang.net', 'mail.tm', 'inboxkitten.com', 'emailfake.com',
        'getairmail.com', 'mailcatch.com', 'spamgourmet.com', 'harakirimail.com', 'mailpoof.com', 'dropmail.me', 'tempinbox.com', 'mvrht.net',
    ];

    /**
     * @return array{ok: bool, reason: ?string, suggestion: ?string}
     */
    public static function check(?string $email, ?bool $dns = null): array
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '') {
            return self::no('Chưa có email');
        }
        if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || str_contains($email, '..')) {
            return self::no('Sai định dạng email');
        }

        [$local, $domain] = [substr($email, 0, strrpos($email, '@')), substr($email, strrpos($email, '@') + 1)];
        $tld = substr($domain, strrpos($domain, '.') + 1);
        if (! str_contains($domain, '.') || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[a-z]{2,24}|xn--[a-z0-9-]+)$/', $domain) || $tld === '') {
            return self::no('Tên miền email không hợp lệ');
        }

        if (isset(self::TYPOS[$domain])) {
            return self::no('Có vẻ gõ nhầm tên miền', $local.'@'.self::TYPOS[$domain]);
        }
        if (in_array($domain, self::PLACEHOLDER_DOMAINS, true) || str_starts_with($domain, 'example.') || in_array($tld, ['test', 'example', 'invalid', 'localhost', 'local'], true)) {
            return self::no('Email mẫu, không phải email thật');
        }
        if (self::isDisposable($domain)) {
            return self::no('Email dùng một lần (hộp thư tạm)');
        }

        $plain = preg_replace('/\+.*$/', '', $local);
        if (in_array($plain, self::PLACEHOLDER_LOCALS, true) || preg_match('/^(.)\1{2,}$/', $plain) || preg_match('/^\d{1,5}$/', $plain)
            || preg_match('/^(test|abc|asdf|qwerty)\d*$/', $plain)) {
            return self::no('Email có vẻ điền cho có');
        }
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true) && strlen($plain) < 6) {
            return self::no('Gmail không có tên ngắn dưới 6 ký tự');
        }

        if (($dns ?? (bool) config('net.reminders.check_dns', true)) && ! in_array($domain, self::COMMON, true) && ! self::domainReceivesMail($domain)) {
            return self::no('Tên miền không nhận được email');
        }

        return ['ok' => true, 'reason' => null, 'suggestion' => null];
    }

    public static function plausible(?string $email, ?bool $dns = null): bool
    {
        return self::check($email, $dns)['ok'];
    }

    private static function isDisposable(string $domain): bool
    {
        foreach (self::DISPOSABLE as $d) {
            if ($domain === $d || str_ends_with($domain, '.'.$d)) {
                return true;
            }
        }

        return false;
    }

    /** Có bản ghi MX (hoặc A, theo chuẩn SMTP) không. Nhớ kết quả 1 ngày. Không tra được DNS (máy chủ chặn) → không chặn gửi. */
    private static function domainReceivesMail(string $domain): bool
    {
        if (! function_exists('checkdnsrr')) {
            return true;
        }

        return Cache::remember('net:email-dns:'.$domain, now()->addDay(), function () use ($domain) {
            $host = function_exists('idn_to_ascii') ? (idn_to_ascii($domain) ?: $domain) : $domain;
            if (@checkdnsrr($host.'.', 'MX') || @checkdnsrr($host.'.', 'A') || @checkdnsrr($host.'.', 'AAAA')) {
                return true;
            }

            // Máy chủ không tra được DNS nào (kể cả gmail.com) → coi như không kiểm tra được, không chặn.
            return ! @checkdnsrr('gmail.com.', 'MX');
        });
    }

    private static function no(string $reason, ?string $suggestion = null): array
    {
        return ['ok' => false, 'reason' => $reason, 'suggestion' => $suggestion];
    }
}
