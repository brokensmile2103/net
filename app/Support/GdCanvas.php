<?php

namespace App\Support;

/**
 * Tờ vẽ GD dùng toạ độ "pixel logic". Vẽ ở độ phân giải gấp $scale lần rồi thu nhỏ khi xuất PNG nên nét cong,
 * viền bo góc, ảnh đại diện tròn… đều mượt (GD không tự khử răng cưa cho hình khối). Không phụ thuộc Laravel.
 */
final class GdCanvas
{
    private \GdImage $img;

    /** @var array<string, int> */
    private array $colors = [];

    public function __construct(
        public readonly int $w,
        public readonly int $h,
        public readonly float $scale,
        private readonly GdText $text,
    ) {
        $this->img = imagecreatetruecolor($this->px($w), $this->px($h));
        imagealphablending($this->img, true);
        imagesavealpha($this->img, false);
    }

    public function __destruct()
    {
        // Phòng khi ném lỗi giữa chừng: GD tự giải phóng ở PHP 8, nhưng gọi sớm cho nhẹ bộ nhớ.
        unset($this->img);
    }

    private function px(float $v): int
    {
        return (int) round($v * $this->scale);
    }

    /** Màu từ mã HEX + độ đậm (1 = đặc, 0 = trong suốt). */
    public function color(string $hex, float $opacity = 1.0): int
    {
        $opacity = max(0.0, min(1.0, $opacity));
        $key = $hex.'|'.$opacity;
        if (! isset($this->colors[$key])) {
            [$r, $g, $b] = Color::rgb($hex);
            $this->colors[$key] = (int) imagecolorallocatealpha($this->img, $r, $g, $b, (int) round(127 * (1 - $opacity)));
        }

        return $this->colors[$key];
    }

    public function fill(string $hex): void
    {
        imagefilledrectangle($this->img, 0, 0, $this->px($this->w), $this->px($this->h), $this->color($hex));
    }

    public function rect(float $x, float $y, float $w, float $h, string $hex, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        imagefilledrectangle($this->img, $this->px($x), $this->px($y), max($this->px($x), $this->px($x + $w) - 1), max($this->px($y), $this->px($y + $h) - 1), $this->color($hex, $opacity));
    }

    /** Khung viền (không tô ruột), nét dày $t nằm TRONG hình chữ nhật. */
    public function frame(float $x, float $y, float $w, float $h, float $t, string $hex, float $opacity = 1.0): void
    {
        $this->rect($x, $y, $w, $t, $hex, $opacity);
        $this->rect($x, $y + $h - $t, $w, $t, $hex, $opacity);
        $this->rect($x, $y + $t, $t, $h - 2 * $t, $hex, $opacity);
        $this->rect($x + $w - $t, $y + $t, $t, $h - 2 * $t, $hex, $opacity);
    }

    /** Hình chữ nhật bo góc — vẽ bằng 1 đa giác nên màu trong suốt cũng không bị chồng đậm ở góc. */
    public function rrect(float $x, float $y, float $w, float $h, float $r, string $hex, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $pts = $this->rrectPoints($x, $y, $w, $h, $r);
        imagefilledpolygon($this->img, $pts, $this->color($hex, $opacity));
    }

    /** Hình bo góc có viền: viền vẽ trước, ruột vẽ sau (ruột phải là màu đặc). */
    public function rrectBordered(float $x, float $y, float $w, float $h, float $r, float $t, string $border, string $fill): void
    {
        $this->rrect($x, $y, $w, $h, $r, $border);
        $this->rrect($x + $t, $y + $t, $w - 2 * $t, $h - 2 * $t, max(0, $r - $t), $fill);
    }

    /** Đường viền hình bo góc, ruột để trống. */
    public function rrectLine(float $x, float $y, float $w, float $h, float $r, float $t, string $hex, float $opacity = 1.0): void
    {
        imagesetthickness($this->img, max(1, $this->px($t)));
        imagepolygon($this->img, $this->rrectPoints($x + $t / 2, $y + $t / 2, $w - $t, $h - $t, max(0, $r - $t / 2)), $this->color($hex, $opacity));
        imagesetthickness($this->img, 1);
    }

    public function ellipse(float $cx, float $cy, float $w, float $h, string $hex, float $opacity = 1.0): void
    {
        imagefilledellipse($this->img, $this->px($cx), $this->px($cy), $this->px($w), $this->px($h), $this->color($hex, $opacity));
    }

    /** @param list<array{0: float, 1: float}> $points */
    public function polygon(array $points, string $hex, float $opacity = 1.0): void
    {
        if (count($points) < 3) {
            return;
        }
        imagefilledpolygon($this->img, $this->flat($points), $this->color($hex, $opacity));
    }

    /** Đường gấp khúc (để vẽ nét tay) — nét dày $t, đầu nét tròn. */
    public function polyline(array $points, float $t, string $hex, float $opacity = 1.0): void
    {
        $col = $this->color($hex, $opacity);
        imagesetthickness($this->img, max(1, $this->px($t)));
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            imageline($this->img, $this->px($points[$i - 1][0]), $this->px($points[$i - 1][1]), $this->px($points[$i][0]), $this->px($points[$i][1]), $col);
        }
        imagesetthickness($this->img, 1);
        $r = max(1.0, $t / 2);
        foreach ([$points[0], end($points)] as [$px, $py]) {
            $this->ellipse($px, $py, $r * 2, $r * 2, $hex, $opacity);
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $t, string $hex, float $opacity = 1.0): void
    {
        if ($y1 === $y2) {
            $this->rect(min($x1, $x2), $y1 - $t / 2, abs($x2 - $x1), $t, $hex, $opacity);
        } elseif ($x1 === $x2) {
            $this->rect($x1 - $t / 2, min($y1, $y2), $t, abs($y2 - $y1), $hex, $opacity);
        } else {
            $this->polyline([[$x1, $y1], [$x2, $y2]], $t, $hex, $opacity);
        }
    }

    /** Đường ngang đứt nét / chấm tròn. */
    public function dashed(float $x1, float $x2, float $y, float $t, float $dash, float $gap, string $hex, float $opacity = 1.0): void
    {
        for ($x = $x1; $x < $x2; $x += $dash + $gap) {
            $this->rect($x, $y - $t / 2, min($dash, $x2 - $x), $t, $hex, $opacity);
        }
    }

    public function dotted(float $x1, float $x2, float $y, float $d, float $gap, string $hex, float $opacity = 1.0): void
    {
        for ($x = $x1 + $d / 2; $x < $x2 - $d / 2; $x += $d + $gap) {
            $this->ellipse($x, $y, $d, $d, $hex, $opacity);
        }
    }

    /** Bóng đổ mềm quanh hình chữ nhật (xếp nhiều lớp mờ). */
    public function shadow(float $x, float $y, float $w, float $h, float $r, float $blur, float $dy, float $strength = 0.12, string $hex = '#000000'): void
    {
        $steps = 10;
        for ($i = $steps; $i >= 1; $i--) {
            $grow = $blur * $i / $steps;
            $this->rrect($x - $grow, $y - $grow + $dy, $w + 2 * $grow, $h + 2 * $grow, $r + $grow, $hex, $strength / $steps * 1.4);
        }
    }

    /**
     * Vẽ khối chữ đã đo bằng GdText::block()/line(). $x là mép trái (align l), mép phải (r) hoặc tâm (c);
     * $top là mép trên khối.
     *
     * @param  array{lines: list<string>, font: string, size: float, w: float, asc: float, lead: float}  $b
     */
    public function text(array $b, float $x, float $top, string $hex, string $align = 'l', float $opacity = 1.0): void
    {
        $col = $this->color($hex, $opacity);
        $path = $this->text->path($b['font']);
        foreach ($b['lines'] as $i => $line) {
            if ($line === '') {
                continue;
            }
            $w = $this->text->width($line, $b['font'], $b['size']);
            $dx = match ($align) {
                'r' => -$w,
                'c' => -$w / 2,
                default => 0,
            };
            imagettftext($this->img, $b['size'] * $this->scale, 0, $this->px($x + $dx), $this->px($top + $b['asc'] + $i * $b['lead']), $col, $path, $line);
        }
    }

    /** Chữ 1 dòng giãn cách (nhãn VIẾT HOA nhỏ). $x tính theo $align như text(). Trả về chiều rộng. */
    public function spaced(string $str, string $font, float $size, float $tracking, float $x, float $top, string $hex, string $align = 'l', float $opacity = 1.0): float
    {
        $width = $this->text->spacedWidth($str, $font, $size, $tracking);
        $cx = match ($align) {
            'r' => $x - $width,
            'c' => $x - $width / 2,
            default => $x,
        };
        $m = $this->text->metrics($font, $size);
        $col = $this->color($hex, $opacity);
        $path = $this->text->path($font);
        foreach (preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if ($ch !== ' ') {
                imagettftext($this->img, $size * $this->scale, 0, $this->px($cx), $this->px($top + $m['asc']), $col, $path, $ch);
            }
            $cx += $this->text->advance($ch, $font, $size) + $tracking;
        }

        return $width;
    }

    /**
     * Ảnh tròn (ảnh đại diện) hoặc chữ viết tắt trên nền màu. $bytes = nội dung file ảnh (hoặc null).
     * Khung tròn $ring px (0 = không viền).
     */
    public function avatar(?string $bytes, string $initials, float $x, float $y, float $size, string $bg, string $fg, string $font, float $ring = 0.0, string $ringHex = '#ffffff'): void
    {
        $d = max(2, $this->px($size));
        $tile = imagecreatetruecolor($d, $d);
        imagealphablending($tile, true);

        $src = $bytes !== null ? @imagecreatefromstring($bytes) : false;
        if ($src instanceof \GdImage) {
            $this->cover($tile, $src, 0, 0, $d, $d);
        } else {
            [$r, $g, $b] = Color::rgb($bg);
            imagefilledrectangle($tile, 0, 0, $d, $d, imagecolorallocate($tile, $r, $g, $b));
            $initials = trim($initials) !== '' ? $initials : 'N';
            $fs = $size * 0.36;
            $blk = $this->text->line($initials, $font, $fs);
            while ($blk['w'] > $size * 0.74 && $fs > 8) {
                $fs -= 2;
                $blk = $this->text->line($initials, $font, $fs);
            }
            [$fr, $fg2, $fb] = Color::rgb($fg);
            $m = $this->text->metrics($font, $fs);
            imagettftext($tile, $fs * $this->scale, 0, (int) round(($d - $blk['w'] * $this->scale) / 2), (int) round(($d + $m['cap'] * $this->scale) / 2), imagecolorallocate($tile, $fr, $fg2, $fb), $this->text->path($font), $initials);
        }

        // Cắt tròn: pixel ngoài hình tròn → trong suốt; vòng ngoài cùng tô màu viền.
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        $clear = imagecolorallocatealpha($tile, 0, 0, 0, 127);
        [$rr, $rg, $rb] = Color::rgb($ringHex);
        $ringCol = imagecolorallocate($tile, $rr, $rg, $rb);
        $rad = $d / 2;
        $ringW = $ring * $this->scale;
        for ($py = 0; $py < $d; $py++) {
            for ($px = 0; $px < $d; $px++) {
                $dist = sqrt(($px + .5 - $rad) ** 2 + ($py + .5 - $rad) ** 2);
                if ($dist > $rad) {
                    imagesetpixel($tile, $px, $py, $clear);
                } elseif ($ringW > 0 && $dist > $rad - $ringW) {
                    imagesetpixel($tile, $px, $py, $ringCol);
                }
            }
        }

        imagealphablending($this->img, true);
        imagecopy($this->img, $tile, $this->px($x), $this->px($y), 0, 0, $d, $d);
        if ($src instanceof \GdImage) {
            imagedestroy($src);
        }
        imagedestroy($tile);
    }

    /**
     * Hạt giấy: rải điểm chấm ngẫu nhiên nhưng cố định theo $seed (cùng nội dung → cùng ảnh), không đụng bộ sinh số của PHP.
     */
    public function grain(float $x, float $y, float $w, float $h, int $seed, float $density, string $hex, float $opacity): void
    {
        $count = (int) round($w * $h * $density);
        $col = $this->color($hex, $opacity);
        $state = ($seed & 0x7fffffff) ?: 1;
        $dot = max(1, $this->px(1));
        for ($i = 0; $i < $count; $i++) {
            $state ^= ($state << 13) & 0x7fffffff;
            $state ^= $state >> 17;
            $state ^= ($state << 5) & 0x7fffffff;
            $px = $this->px($x) + ($state % max(1, $this->px($w)));
            $state ^= ($state << 13) & 0x7fffffff;
            $state ^= $state >> 17;
            $state ^= ($state << 5) & 0x7fffffff;
            $py = $this->px($y) + ($state % max(1, $this->px($h)));
            imagefilledrectangle($this->img, $px, $py, $px + $dot - 1, $py + $dot - 1, $col);
        }
    }

    /** Xuất PNG (thu nhỏ về kích thước logic nếu đã vẽ ở độ phân giải cao). */
    public function png(): string
    {
        $out = $this->img;
        $scaled = null;
        if ($this->scale !== 1.0) {
            $scaled = imagecreatetruecolor($this->w, $this->h);
            imagecopyresampled($scaled, $this->img, 0, 0, 0, 0, $this->w, $this->h, imagesx($this->img), imagesy($this->img));
            $out = $scaled;
        }

        ob_start();
        imagepng($out, null, 6);
        $png = (string) ob_get_clean();

        if ($scaled) {
            imagedestroy($scaled);
        }

        return $png;
    }

    /** Vẽ $src phủ kín khung (x, y, w, h), cắt phần thừa ở giữa — như object-fit: cover. */
    private function cover(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $k = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $k);
        $ch = (int) round($h / $k);
        imagecopyresampled($dst, $src, $x, $y, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, max(1, $cw), max(1, $ch));
    }

    /** @return list<int> */
    private function rrectPoints(float $x, float $y, float $w, float $h, float $r): array
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        $corners = [
            [$x + $w - $r, $y + $r, -90],
            [$x + $w - $r, $y + $h - $r, 0],
            [$x + $r, $y + $h - $r, 90],
            [$x + $r, $y + $r, 180],
        ];
        $pts = [];
        $steps = $r > 0 ? max(4, (int) ceil($r * $this->scale / 3)) : 1;
        foreach ($corners as [$cx, $cy, $from]) {
            for ($i = 0; $i <= $steps; $i++) {
                $a = deg2rad($from + 90 * $i / $steps);
                $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
            }
        }

        return $this->flat($pts);
    }

    /** @param list<array{0: float, 1: float}> $points @return list<int> */
    private function flat(array $points): array
    {
        $flat = [];
        foreach ($points as [$px, $py]) {
            $flat[] = $this->px($px);
            $flat[] = $this->px($py);
        }

        return $flat;
    }
}
