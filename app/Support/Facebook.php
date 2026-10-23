<?php

namespace App\Support;

/**
 * Chuẩn hoá thông tin Facebook của khách để (1) hiện thành link bấm được, (2) NHẬN RA khách cũ.
 *
 * Khách gõ đủ kiểu: "facebook.com/huy.design", "https://m.facebook.com/huy.design?ref=bookmarks",
 * "fb.com/huy.design", "@huy.design", "m.me/huy.design", "facebook.com/profile.php?id=1000123",
 * hoặc chỉ ghi TÊN Facebook ("Nguyễn Văn Huy"). Tất cả link/username được đưa về 1 dạng duy nhất:
 *   - username → https://www.facebook.com/huy.design   (chữ thường — username Facebook không phân biệt hoa thường)
 *   - ID số    → https://www.facebook.com/profile.php?id=1000123
 *   - link khác của Facebook (share/…, groups/…) → https://www.facebook.com/<đường dẫn gốc>
 * Tên Facebook (chữ tự do) được giữ nguyên để freelancer tìm, nhưng KHÔNG dùng để tự ghép khách
 * — quá nhiều người trùng tên.
 */
final class Facebook
{
    private const BASE = 'https://www.facebook.com/';

    /** Đoạn đầu đường dẫn không phải username. */
    private const NON_PROFILE_PATHS = [
        'share', 'groups', 'pages', 'p', 'watch', 'events', 'story.php', 'permalink.php', 'photo', 'photo.php',
        'photos', 'reel', 'reels', 'marketplace', 'login', 'login.php', 'home.php', 'hashtag', 'stories', 'posts', 'gaming',
    ];

    /** Rỗng → null; link/username → dạng chuẩn; chữ tự do → giữ nguyên (đã gọn khoảng trắng). */
    public static function normalize(?string $input): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $input));
        if ($value === '') {
            return null;
        }

        // ID số (không bắt đầu bằng 0 — để số điện thoại gõ nhầm ô không bị hiểu thành ID).
        $bare = ltrim($value, '@');
        if (preg_match('/^[1-9]\d{4,24}$/', $bare)) {
            return self::BASE.'profile.php?id='.$bare;
        }

        // Username trần: "@huydesign" hoặc "huy.design" / "huy2024" (có dấu chấm hoặc số). Một chữ trơn như "Hoang"
        // nhiều khả năng là TÊN chứ không phải username → giữ dạng chữ, tránh tạo link tới nhầm người.
        $hasAt = str_starts_with($value, '@');
        if (preg_match('/^[A-Za-z0-9.]{5,50}$/', $bare) && preg_match('/[A-Za-z]/', $bare) && ! str_contains($bare, '..')
            && ($hasAt || preg_match('/[.\d]/', $bare)) && ! preg_match('/\.(com|net|org|vn|me|io|co)$/i', $bare)) {
            return self::BASE.strtolower(trim($bare, '.'));
        }

        return self::parseFacebookUrl($value) ?? $value;
    }

    /** Người dùng dán link KHÔNG phải Facebook (Instagram, Zalo…) hoặc link Facebook không trỏ tới ai → báo lỗi thay vì lưu sai. */
    public static function isForeignUrl(?string $input): bool
    {
        $value = trim((string) $input);
        if ($value === '' || ! self::looksLikeUrl($value)) {
            return false;
        }

        return ! self::isLink(self::normalize($value));
    }

    /** Giá trị đã chuẩn hoá có phải link Facebook (bấm được, dùng để nhận ra khách) không. */
    public static function isLink(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::BASE);
    }

    public static function url(?string $value): ?string
    {
        return self::isLink($value) ? $value : null;
    }

    /** Chữ hiển thị: username / "ID 1000…" / tên Facebook khách tự ghi. */
    public static function label(?string $value): string
    {
        if (! self::isLink($value)) {
            return (string) $value;
        }

        $path = substr($value, strlen(self::BASE));
        if (preg_match('/^profile\.php\?id=(\d+)$/', $path, $m)) {
            return 'Facebook ID '.$m[1];
        }

        return preg_match('/^[a-z0-9.]+$/', $path) ? $path : 'Facebook';
    }

    /** Link mở thẳng khung chat Messenger (chỉ có với username / ID). */
    public static function messengerUrl(?string $value): ?string
    {
        if (! self::isLink($value)) {
            return null;
        }

        $path = substr($value, strlen(self::BASE));
        if (preg_match('/^profile\.php\?id=(\d+)$/', $path, $m)) {
            return 'https://m.me/'.$m[1];
        }

        return preg_match('/^[a-z0-9.]+$/', $path) ? 'https://m.me/'.$path : null;
    }

    // ------------------------------------------------------------------

    private static function looksLikeUrl(string $value): bool
    {
        return (bool) preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)
            || (bool) preg_match('#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/|$)#i', $value);
    }

    /** Link Facebook → dạng chuẩn; không phải link Facebook → null. */
    private static function parseFacebookUrl(string $value): ?string
    {
        if (! self::looksLikeUrl($value)) {
            return null;
        }

        $withScheme = preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) ? $value : 'https://'.$value;
        $parts = parse_url($withScheme);
        if (! is_array($parts) || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $isFacebook = in_array($host, ['facebook.com', 'fb.com', 'fb.me', 'm.me', 'messenger.com', 'www.messenger.com'], true)
            || str_ends_with($host, '.facebook.com') || str_ends_with($host, '.fb.com');
        if (! $isFacebook) {
            return null;
        }

        $path = trim(rawurldecode($parts['path'] ?? ''), '/');
        parse_str($parts['query'] ?? '', $query);
        $segments = $path === '' ? [] : explode('/', $path);

        // m.me/<username>, messenger.com/t/<username>, facebook.com/messages/t/<username>
        if ($host === 'm.me' || $host === 'fb.me') {
            $handle = $segments[0] ?? '';
        } elseif (str_contains($host, 'messenger.com')) {
            $handle = ($segments[0] ?? '') === 't' ? ($segments[1] ?? '') : '';
        } elseif (($segments[0] ?? '') === 'messages' && ($segments[1] ?? '') === 't') {
            $handle = $segments[2] ?? '';
        } elseif (strtolower($segments[0] ?? '') === 'profile.php') {
            $id = (string) ($query['id'] ?? '');

            return preg_match('/^[1-9]\d{4,24}$/', $id) ? self::BASE.'profile.php?id='.$id : null;
        } elseif (strtolower($segments[0] ?? '') === 'people' && isset($segments[2]) && preg_match('/^[1-9]\d{4,24}$/', $segments[2])) {
            // facebook.com/people/Ten-Nguoi/1000123/
            return self::BASE.'profile.php?id='.$segments[2];
        } elseif (isset($segments[0]) && in_array(strtolower($segments[0]), self::NON_PROFILE_PATHS, true)) {
            // Link chia sẻ / nhóm / bài viết: giữ nguyên đường dẫn (có thể phân biệt hoa thường), bỏ tham số theo dõi.
            return self::BASE.$path;
        } else {
            $handle = $segments[0] ?? '';
        }

        if (preg_match('/^[1-9]\d{4,24}$/', $handle)) {
            return self::BASE.'profile.php?id='.$handle;
        }
        if (preg_match('/^[A-Za-z0-9.]{1,50}$/', $handle) && preg_match('/[A-Za-z0-9]/', $handle)) {
            return self::BASE.strtolower(trim($handle, '.'));
        }

        // facebook.com không kèm đường dẫn → vô nghĩa, coi như link không hợp lệ.
        return null;
    }
}
