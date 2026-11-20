<?php

namespace App\Support;

use App\Models\Studio;

/**
 * Bộ "mã thiết kế" cho email gửi khách — đi theo MẪU GIAO DIỆN trang đặt đơn và màu nhấn của studio
 * (resources/views/emails/studio.blade.php). Chỉ dùng thứ hộp thư hỗ trợ được: bảng, style inline, font dự phòng
 * an toàn (Gmail/Outlook bỏ webfont thì vẫn ra font hệ thống gần giống).
 */
final class EmailTheme
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Studio $studio, ?string $theme = null): array
    {
        $theme = array_key_exists((string) $theme, config('net.page_themes')) ? $theme : $studio->pageTheme();
        $p = Color::palette($studio->accentHex());
        $accent = $p['--accent'];
        $ink = '#1d1d22';

        $sans = "'Be Vietnam Pro', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
        $base = [
            'key' => $theme,
            'accent' => $accent,
            'accent_ink' => $p['--accent-ink'],
            'on_accent' => $p['--on-accent'],
            'soft' => $p['--accent-soft'],
            'soft_line' => $p['--accent-line'],
            'second' => $p['--ink-riso'],
            'ink' => $ink,
            'ink2' => '#3c3c44',
            'muted' => '#6b6b74',
            'line' => '#e6e4df',
            'page_bg' => '#f4f3ef',
            'card_bg' => '#ffffff',
            'card_border' => '1px solid #e6e4df',
            'radius' => '14px',
            'btn_radius' => '10px',
            'btn_shadow' => 'none',
            'font' => $sans,
            'head_font' => $sans,
            'head_size' => '26px',
            'head_weight' => '800',
            'head_style' => 'normal',
            'head_spacing' => '-0.4px',
            'eyebrow_font' => $sans,
            'fonts_url' => 'https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;800&display=swap',
            'shades' => [],
        ];

        return match ($theme) {
            'artboard' => array_merge($base, [
                'page_bg' => '#e9e9ee', 'card_border' => '1px solid '.$accent, 'radius' => '2px', 'btn_radius' => '6px',
                'head_font' => "'Space Grotesk', 'Segoe UI', Helvetica, Arial, sans-serif", 'head_spacing' => '-0.6px',
                'eyebrow_font' => "'JetBrains Mono', 'SFMono-Regular', Consolas, 'Courier New', monospace",
                'fonts_url' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=JetBrains+Mono:wght@500&family=Be+Vietnam+Pro:wght@400;600&display=swap',
            ]),
            'riso' => array_merge($base, [
                'page_bg' => '#f1efe9', 'card_bg' => '#fbf9f4', 'card_border' => '2px solid '.$ink, 'radius' => '4px', 'btn_radius' => '4px',
                'btn_shadow' => '4px 4px 0 '.$p['--ink-riso'], 'head_font' => "'Archivo', 'Arial Black', Helvetica, Arial, sans-serif", 'head_weight' => '800',
                'face' => $p['--riso-face'], 'face_shadow' => $p['--riso-shadow'],
                'fonts_url' => 'https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&family=Be+Vietnam+Pro:wght@400;600&display=swap',
            ]),
            'swiss' => array_merge($base, [
                'page_bg' => '#ffffff', 'card_border' => 'none', 'radius' => '0', 'btn_radius' => '0',
                'font' => "Helvetica, Arial, 'Be Vietnam Pro', sans-serif", 'head_font' => "'Archivo', Helvetica, Arial, sans-serif",
                'head_size' => '34px', 'head_weight' => '800', 'head_spacing' => '-1.2px',
                'fonts_url' => 'https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&display=swap',
            ]),
            'sketch' => array_merge($base, [
                'page_bg' => '#f3f1e7', 'card_bg' => '#fffef8', 'card_border' => '2px dashed #2b2b33', 'radius' => '12px', 'btn_radius' => '999px',
                'head_font' => "'Patrick Hand', 'Comic Sans MS', 'Chalkboard SE', cursive", 'head_size' => '30px', 'head_weight' => '400', 'head_spacing' => '0',
                'eyebrow_font' => "'Patrick Hand', 'Comic Sans MS', cursive",
                'fonts_url' => 'https://fonts.googleapis.com/css2?family=Patrick+Hand&family=Be+Vietnam+Pro:wght@400;600&display=swap',
            ]),
            'editorial' => array_merge($base, [
                'page_bg' => '#f5f2ec', 'card_bg' => '#fffdf9', 'card_border' => 'none', 'radius' => '0', 'btn_radius' => '0',
                'font' => "Georgia, 'Times New Roman', serif", 'head_font' => "'Fraunces', Georgia, 'Times New Roman', serif",
                'head_size' => '32px', 'head_weight' => '700', 'head_style' => 'normal', 'head_spacing' => '-0.6px',
                'eyebrow_font' => "'Be Vietnam Pro', Helvetica, Arial, sans-serif",
                'fonts_url' => 'https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;1,400&family=Be+Vietnam+Pro:wght@500;600&display=swap',
            ]),
            'swatch' => array_merge($base, [
                'page_bg' => '#efeeea', 'radius' => '6px', 'btn_radius' => '6px',
                'eyebrow_font' => "'JetBrains Mono', 'SFMono-Regular', Consolas, monospace",
                'fonts_url' => 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Be+Vietnam+Pro:wght@400;600;800&display=swap',
                'shades' => array_map(fn ($t) => ['pct' => (int) round((1 - $t) * 100), 'hex' => $t > 0 ? Color::mix($accent, '#ffffff', $t) : $accent], [0, 0.2, 0.4, 0.6, 0.8]),
            ]),
            default => $base,
        };
    }
}
