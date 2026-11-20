<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "Sổ phác thảo": giấy kẻ ô, chữ viết tay, nét bút dạ run nhẹ, nét highlight màu nhấn, băng keo. */
final class SketchTheme extends Theme
{
    private const INK = '#2a2d34';

    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $M = 56;
        $cw = self::W - 2 * $M;
        $padX = 36;
        $padY = 26;

        $avatar = 92;
        $tx = $M + $avatar + 30;
        $tw = self::W - $M - $tx;
        $name = $t->block($d['name'], 'hand', 84, $tw - 20, 2, 48, 1.0);
        $head = $d['headline'] !== '' ? $t->block($d['headline'], 'hand', 30, $tw, 2, 22, 1.05) : null;
        $headerH = max($avatar + 16, $name['h'] + ($head ? 14 + $head['h'] : 0));
        $sec = $t->line('Bảng giá', 'hand', 56);

        $rows = $this->rows($t, $d, $cw - 2 * $padX, [
            'nameFont' => 'hand', 'nameSize' => 38, 'nameMin' => 28, 'nameLines' => 3, 'nameLead' => 1.0,
            'priceFont' => 'hand', 'priceSize' => 40, 'priceMin' => 26, 'priceFontUnknown' => 'hand', 'priceSizeUnknown' => 28,
            'priceExtra' => 16,
            'descFont' => 'hand', 'descSize' => 25, 'descLines' => 3, 'descLead' => 1.0,
            'metaFont' => 'hand', 'metaSize' => 22, 'gap' => 28,
        ]);
        $heights = array_map(fn ($r) => $padY * 2 + max($this->stackHeight($r, 6, 6), $r['price']['h']), $rows);
        $cardH = array_sum($heights);

        $cta = $this->cta($d);
        $chipH = 56;
        $ctaBlk = $cta ? $t->line('Đặt thiết kế', 'hand', 32) : null;
        $chipW = $ctaBlk ? $ctaBlk['w'] + 56 : 0;
        $url = $d['url'] ? $t->block($d['url'], 'hand', 31, $cw - $chipW - 24 - 200, 1, 22) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'hand', 27, $cw, 1, 20) : null;
        $updated = $t->line($this->updated($d), 'hand', 24);
        $more = $d['more'] > 0 ? $t->line('… và '.$d['more'].' dịch vụ khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'hand', 26) : null;

        $y = $M + 10;
        $headY = $y;
        $y += $headerH + 22;
        $ruleY = $y;
        $secY = $y + 26;
        $y = $secY + $sec['h'] + 26;
        $cardY = $y;
        $y += $cardH + 8;
        $moreY = null;
        if ($more) {
            $moreY = $y + 18;
            $y += 18 + $more['h'];
        }
        $footY = $y + 36;
        $y = $footY + ($cta ? $chipH : 0) + ($contact ? ($cta ? 18 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);
        $height = $y + $M;
        $seed = crc32($d['name']);

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $t, $d, $pal, $M, $cw, $padX, $padY, $avatar, $tx, $name, $head, $headY, $sec, $rows, $heights, $ruleY, $secY, $cardY, $cardH,
            $cta, $ctaBlk, $chipH, $chipW, $url, $contact, $updated, $more, $moreY, $footY, $seed
        ) {
            $ink = self::INK;
            $c->fill('#fcfcfa');
            for ($g = 0; $g < $c->w; $g += 26) {
                $c->rect($g, 0, 1, $c->h, '#dfe7f0');
            }
            for ($g = 0; $g < $c->h; $g += 26) {
                $c->rect(0, $g, $c->w, 1, '#dfe7f0');
            }

            // Ảnh đại diện + băng keo
            $ay = $headY + 8;
            $c->avatar($d['avatar'], $d['initials'], $M, $ay, $avatar, $d['accent'], $pal['--on-accent'], 'hand', 4, $ink);
            $this->tape($c, $M + $avatar / 2, $ay - 2, 96, 24);

            // Tên có nét highlight dưới
            $y = $headY;
            $off = 0.0;
            foreach ($name['lines'] as $i => $line) {
                $lw = $t->width($line, 'hand', $name['size']);
                $ly = $y + $i * $name['lead'];
                $lx = $tx + 8;
                $h = $name['asc'] + $name['desc'];
                $tilt = $lw * 0.02;
                $c->polygon([[$lx - 6, $ly + $h * 0.58 + $tilt], [$lx + $lw + 6, $ly + $h * 0.58], [$lx + $lw + 6, $ly + $h * 0.9], [$lx - 6, $ly + $h * 0.9 + $tilt]], $d['accent'], 0.38);
            }
            $c->text($name, $tx + 8, $y, $ink);
            if ($head) {
                $c->text($head, $tx + 8, $y + $name['h'] + 14, '#464a54');
            }

            // Vạch ngăn nét tay + tiêu đề mục có gạch chân uốn
            $c->polyline($this->wobble($M, $ruleY, $M + $cw, $ruleY, $seed + 1), 3, $ink);
            $c->text($sec, $M, $secY, $ink);
            $uy = $secY + $sec['h'] - 2;
            $pts = [];
            for ($x = 0; $x <= $sec['w'] + 20; $x += 6) {
                $pts[] = [$M + $x, $uy + sin($x / 9) * 2.2];
            }
            $c->polyline($pts, 4, $d['accent'], 0.9);

            // Khung bảng giá nét tay
            $card = $this->box($M, $cardY, $cw, $cardH, $seed + 7);
            $c->polygon($card, '#ffffff');
            $c->polyline(array_merge($card, [$card[0]]), 2.6, $ink);
            $ry = $cardY;
            foreach ($rows as $i => $r) {
                if ($i > 0) {
                    $c->dashed($M + 8, $M + $cw - 8, $ry, 2, 11, 8, $ink, 0.55);
                }
                $iy = $ry + $padY;
                $this->paintStack($c, $r, $M + $padX, $iy, ['name' => $ink, 'desc' => '#464a54', 'meta' => '#6b7079'], 6, 6);
                $right = $M + $cw - $padX;
                $pTop = $this->priceTop($r, $iy);
                if ($r['known']) {
                    $px = $right - $r['price']['w'];
                    $ph = $r['price']['asc'] + $r['price']['desc'];
                    $c->polygon([[$px - 8, $pTop + $ph * 0.55 + 2], [$right + 8, $pTop + $ph * 0.55], [$right + 8, $pTop + $ph * 0.92], [$px - 8, $pTop + $ph * 0.92 + 2]], $d['accent'], 0.38);
                }
                $c->text($r['price'], $right, $pTop, $r['known'] ? $ink : '#6b7079', 'r');
                $ry += $heights[$i];
            }

            if ($more) {
                $c->text($more, $M, $moreY, '#464a54');
            }

            $fy = $footY;
            if ($cta) {
                $btn = $this->box($M, $fy, $chipW, $chipH, $seed + 13);
                $c->polygon($btn, $d['accent']);
                $c->polyline(array_merge($btn, [$btn[0]]), 2.6, $ink);
                $c->text($ctaBlk, $M + 28, $fy + ($chipH - $ctaBlk['h']) / 2 + 1, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $M + $chipW + 24, $fy + ($chipH - $url['h']) / 2, $ink);
                }
                $c->text($updated, $M + $cw, $fy + ($chipH - $updated['h']) / 2, '#6b7079', 'r');
                $fy += $chipH + 18;
            }
            if ($contact) {
                $c->text($contact, $M, $fy, '#464a54');
                if (! $cta) {
                    $c->text($updated, $M + $cw, $fy + ($contact['h'] - $updated['h']) / 2, '#6b7079', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $M + $cw, $fy, '#6b7079', 'r');
            }
        }];
    }

    /** Băng keo vàng nghiêng nhẹ (-3°). */
    private function tape(GdCanvas $c, float $cx, float $cy, float $w, float $h): void
    {
        $a = deg2rad(-3);
        $pts = [];
        foreach ([[-$w / 2, -$h / 2], [$w / 2, -$h / 2], [$w / 2, $h / 2], [-$w / 2, $h / 2]] as [$dx, $dy]) {
            $pts[] = [$cx + $dx * cos($a) - $dy * sin($a), $cy + $dx * sin($a) + $dy * cos($a)];
        }
        $c->polygon($pts, '#f6e084', 0.82);
    }

    /** Đường thẳng run nhẹ như vẽ tay (ổn định theo $seed). */
    private function wobble(float $x1, float $y1, float $x2, float $y2, int $seed): array
    {
        $pts = [];
        $n = max(2, (int) ceil(hypot($x2 - $x1, $y2 - $y1) / 40));
        for ($i = 0; $i <= $n; $i++) {
            $k = $i / $n;
            $pts[] = [$x1 + ($x2 - $x1) * $k, $y1 + ($y2 - $y1) * $k + ($i === 0 || $i === $n ? 0 : $this->jit($seed + $i) * 1.6)];
        }

        return $pts;
    }

    /** Hình chữ nhật bo góc, cạnh run nhẹ — danh sách điểm khép kín. */
    private function box(float $x, float $y, float $w, float $h, int $seed): array
    {
        $r = 16.0;
        $pts = [];
        $edge = function (float $x1, float $y1, float $x2, float $y2, int $s) use (&$pts) {
            $n = max(1, (int) ceil(hypot($x2 - $x1, $y2 - $y1) / 50));
            for ($i = 0; $i < $n; $i++) {
                $k = $i / $n;
                $j = $this->jit($s + $i) * 1.5;
                $pts[] = [$x1 + ($x2 - $x1) * $k + ($y1 === $y2 ? 0 : $j), $y1 + ($y2 - $y1) * $k + ($y1 === $y2 ? $j : 0)];
            }
        };
        $corner = function (float $cx, float $cy, float $from) use (&$pts) {
            for ($i = 0; $i <= 6; $i++) {
                $a = deg2rad($from + 90 * $i / 6);
                $pts[] = [$cx + 16 * cos($a), $cy + 16 * sin($a)];
            }
        };
        $edge($x + $r, $y, $x + $w - $r, $y, $seed);
        $corner($x + $w - $r, $y + $r, -90);
        $edge($x + $w, $y + $r, $x + $w, $y + $h - $r, $seed + 40);
        $corner($x + $w - $r, $y + $h - $r, 0);
        $edge($x + $w - $r, $y + $h, $x + $r, $y + $h, $seed + 80);
        $corner($x + $r, $y + $h - $r, 90);
        $edge($x, $y + $h - $r, $x, $y + $r, $seed + 120);
        $corner($x + $r, $y + $r, 180);

        return $pts;
    }

    /** Số giả ngẫu nhiên ổn định trong [-1, 1]. */
    private function jit(int $n): float
    {
        return sin($n * 12.9898 + 78.233) * 0.5 + cos($n * 4.1414) * 0.5;
    }
}
