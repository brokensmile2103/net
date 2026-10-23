<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lớp bảo vệ form chống bot spam — 2 kỹ thuật kết hợp, không cần dịch vụ bên
 * ngoài (không cần Google reCAPTCHA).
 *
 * 1. Honeypot: 1 trường ẩn mà người dùng thật không bao giờ điền. Để trình
 *    duyệt/password manager (Chrome, 1Password, Bitwarden...) KHÔNG tự điền
 *    nhầm giá trị đã lưu vào đây, áp dụng 3 lớp:
 *      a) `readonly` — browser KHÔNG autofill vào input readonly (hành vi
 *         chuẩn, không phải hack). Đây là lớp chặn quan trọng nhất.
 *      b) Tên field NGẪU NHIÊN mỗi lần hiển thị form (không cố định là
 *         "website"/"note_ref" nữa) — vì heuristic autofill của browser có
 *         thể match theo TYPE ("text") bất kể vị trí hay tên, nên tên ngẫu
 *         nhiên + không nằm trong danh sách từ khoá quen thuộc giúp tránh bị
 *         nhận diện là ô username.
 *      c) Đặt cuối form, off-screen bằng CSS, tabindex="-1", aria-hidden.
 *    Tên field ngẫu nhiên được ký (HMAC) CHUNG với token thời gian bên dưới,
 *    nên nếu bot cố tình đổi giá trị field name để né honeypot, chữ ký sẽ
 *    sai và bị chặn ở bước verify() luôn.
 *
 * 2. Token thời gian ký HMAC riêng cho TỪNG FORM: gắn timestamp lúc form
 *    được hiển thị (GET), ký bằng khoá dẫn xuất riêng cho từng $formName —
 *    token sinh cho form "register" không thể đem dùng giả mạo form "login".
 *    Token tự hết hạn sau 1 giờ (chặn cứng). Submit "quá nhanh" (dưới
 *    MIN_SECONDS_TO_SUBMIT giây) CHỈ ghi log để theo dõi chứ KHÔNG chặn cứng
 *    — vì đây là suy đoán yếu, dễ false-positive với người dùng thật dùng
 *    autofill/password manager/Face ID điền sẵn rồi bấm ngay.
 */
class FormGuard
{
    private const EXPIRES_AFTER_SECONDS = 3600;

    private const MIN_SECONDS_TO_SUBMIT = 2;

    /**
     * Sinh token mới cho 1 form cụ thể — gọi lúc HIỂN THỊ form (GET), không
     * phải lúc submit. Bao gồm cả tên field honeypot ngẫu nhiên cho lần hiển
     * thị này.
     */
    public static function generate(string $formName): array
    {
        $timestamp = time();
        $honeypotField = 'f_'.bin2hex(random_bytes(4));

        return [
            'timestamp' => $timestamp,
            'honeypot_field' => $honeypotField,
            'signature' => self::sign($formName, $timestamp, $honeypotField),
        ];
    }

    /**
     * Kiểm tra token + honeypot khi form submit — trả về true nếu request
     * HỢP LỆ (không phải bot). $honeypotField là tên field honeypot mà
     * client báo lại (từ hidden input "hp_key"); giá trị thực của field đó
     * được lấy trực tiếp từ $request bằng chính tên này.
     */
    public static function verify(Request $request, string $formName, ?string $timestamp, ?string $signature, ?string $honeypotField): bool
    {
        if (! $timestamp || ! $signature || ! ctype_digit((string) $timestamp) || ! $honeypotField) {
            self::logRejected($formName, 'missing_or_invalid_token');

            return false;
        }

        $expected = self::sign($formName, (int) $timestamp, $honeypotField);

        if (! hash_equals($expected, (string) $signature)) {
            // Sai chữ ký = hoặc token giả mạo, hoặc bot đổi tên honeypot_field
            // để né, hoặc APP_KEY lệch giữa các server/instance (nếu chạy
            // nhiều server sau load balancer, kiểm tra APP_KEY đồng bộ).
            self::logRejected($formName, 'signature_mismatch');

            return false;
        }

        // Honeypot: field ngẫu nhiên này CHỈ được bot/autofill điền, người
        // dùng thật không bao giờ thấy để điền vào.
        if ($request->filled($honeypotField)) {
            self::logRejected($formName, 'honeypot_filled');

            return false;
        }

        $elapsed = time() - (int) $timestamp;

        if ($elapsed > self::EXPIRES_AFTER_SECONDS) {
            self::logRejected($formName, 'token_expired', ['elapsed_seconds' => $elapsed]);

            return false;
        }

        if ($elapsed < self::MIN_SECONDS_TO_SUBMIT) {
            // Chỉ ghi log để theo dõi, KHÔNG chặn — tránh false-positive với
            // người dùng thật dùng autofill/password manager.
            Log::info('form_guard.fast_submit', [
                'form' => $formName,
                'elapsed_seconds' => $elapsed,
            ]);
        }

        return true;
    }

    /**
     * Chữ ký HMAC riêng cho từng $formName — khoá ký dẫn xuất từ APP_KEY trộn
     * với tên form (2 lớp HMAC), nên đổi APP_KEY làm mất hiệu lực toàn bộ
     * token cũ ngay lập tức. Ký luôn cả tên field honeypot để chống giả mạo.
     */
    private static function sign(string $formName, int $timestamp, string $honeypotField): string
    {
        $formKey = hash_hmac('sha256', $formName, (string) config('app.key'));

        return hash_hmac('sha256', $formName.'|'.$timestamp.'|'.$honeypotField, $formKey);
    }

    private static function logRejected(string $formName, string $reason, array $context = []): void
    {
        Log::warning('form_guard.rejected', array_merge([
            'form' => $formName,
            'reason' => $reason,
        ], $context));
    }
}
