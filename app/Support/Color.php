<?php

namespace App\Support;

/**
 * Từ 1 màu nhấn bất kỳ (mã HEX designer tự chọn) suy ra cả bộ màu giao diện cần: màu khi rê chuột,
 * màu chữ nhấn đọc được trên nền trắng, nền nhạt, viền nhạt, và màu chữ đặt TRÊN nền nhấn (trắng hay mực)
 * — để màu vàng chanh hay xanh bạc hà vẫn đọc rõ, đúng chuẩn tương phản WCAG.
 */
final class Color
{
    public static function normalize(?string $hex): ?string
    {
        $hex = strtolower(trim((string) $hex));
        if (preg_match('/^#?([0-9a-f]{3})$/', $hex, $m)) {
            $hex = '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        }
        if (preg_match('/^#?([0-9a-f]{6})$/', $hex, $m)) {
            return '#'.$m[1];
        }

        return null;
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex) ?? '#000000', '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hex(array $rgb): string
    {
        return '#'.implode('', array_map(fn ($c) => str_pad(dechex((int) max(0, min(255, round($c)))), 2, '0', STR_PAD_LEFT), $rgb));
    }

    /** Trộn $a với $b, $t = 0 → $a, 1 → $b. */
    public static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);

        return self::hex([$r1 + ($r2 - $r1) * $t, $g1 + ($g2 - $g1) * $t, $b1 + ($b2 - $b1) * $t]);
    }

    public static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** Sắc độ (0–360) để chọn màu mực thứ 2 cho mẫu Risograph. */
    public static function hue(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $d = $max - $min;
        if ($d == 0) {
            return 0;
        }
        $h = match ($max) {
            $r => fmod(($g - $b) / $d, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return fmod($h * 60 + 360, 360);
    }

    /**
     * Bộ biến CSS cho 1 màu nhấn.
     *
     * @return array<string, string>
     */
    public static function palette(string $accent, string $ink = '#1d1d22'): array
    {
        $accent = self::normalize($accent) ?? '#c8106e';

        // Chữ màu nhấn trên nền trắng: tối dần tới khi đủ 4.5:1.
        $text = $accent;
        for ($i = 1; $i <= 20 && self::contrast($text, '#ffffff') < 4.5; $i++) {
            $text = self::mix($accent, '#000000', $i * 0.05);
        }

        $on = self::contrast($accent, '#ffffff') >= 3 ? '#ffffff' : $ink;
        $hover = self::luminance($accent) > 0.5 ? self::mix($accent, '#000000', 0.12) : self::mix($accent, '#000000', 0.18);

        // Mực thứ 2 cho Risograph: màu nhấn thiên xanh lam → hồng huỳnh quang, còn lại → xanh lam Riso.
        $hue = self::hue($accent);
        $riso2 = ($hue >= 170 && $hue <= 260) ? '#ff48b0' : '#0078bf';

        return [
            '--accent' => $accent,
            '--accent-hover' => $hover,
            '--accent-ink' => $text,
            '--accent-soft' => self::mix($accent, '#ffffff', 0.9),
            '--accent-line' => self::mix($accent, '#ffffff', 0.68),
            '--on-accent' => $on,
            '--ink-riso' => $riso2,
            '--on-riso' => $riso2 === '#ff48b0' ? $ink : '#ffffff',
            // Tên studio ở mẫu Riso: màu nhấn quá nhạt trên nền giấy thì đảo lớp — mực 2 làm chữ, màu nhấn làm bóng lệch khuôn.
            '--riso-face' => self::contrast($accent, '#f1efe9') >= 3 ? $accent : $riso2,
            '--riso-shadow' => self::contrast($accent, '#f1efe9') >= 3 ? $riso2 : $accent,
        ];
    }

    /** "--a:#fff;--b:#000" để đặt thẳng vào thuộc tính style. Chỉ chứa mã HEX đã kiểm tra — an toàn để in ra HTML. */
    public static function styleAttr(array $vars): string
    {
        return implode(';', array_map(fn ($k, $v) => $k.':'.$v, array_keys($vars), $vars));
    }
}
