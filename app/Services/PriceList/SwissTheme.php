<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "Lưới Thuỵ Sĩ": nền trắng, chữ đen cực đậm, vạch kẻ dày 3px, khối màu nhấn dứt khoát, chân trang đen. */
final class SwissTheme extends Theme
{
    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $M = 56;
        $cw = self::W - 2 * $M;
        $rule = 3;

        // Dải nhãn đầu trang
        $top = $t->line('BẢNG GIÁ', 'swissb', 14);
        $topH = $top['h'];

        // Khối màu (4/12 cột) + tên (8/12 cột)
        $blockW = 347;
        $nx = $M + $blockW + 24;
        $nw = self::W - $M - $nx;
        $name = $t->block($d['name'], 'swissb', 112, $nw, 3, 54, 0.88);
        $blockH = max(300.0, $name['h'] + 8);
        $tagBlock = $d['tagline'] !== '' ? $t->block($d['tagline'], 'swissb', 26, $blockW - 48, 3, 18, 1.0) : null;
        $head = $d['headline'] !== '' && $d['headline'] !== $d['tagline'] ? $t->block($d['headline'], 'swissb', 32, $cw * 0.86, 2, 22, 1.02) : null;

        $sec = $t->line('Bảng giá', 'swissb', 48);
        $count = $t->line((count($d['services']) + $d['more']).' dịch vụ', 'swiss', 18);

        $idxW = 88;
        $rows = $this->rows($t, $d, $cw - $idxW, [
            'nameFont' => 'swissb', 'nameSize' => 35, 'nameMin' => 26, 'nameLines' => 3, 'nameLead' => 0.98,
            'priceFont' => 'swissb', 'priceSize' => 35, 'priceMin' => 24, 'priceFontUnknown' => 'swiss', 'priceSizeUnknown' => 23,
            'descFont' => 'body', 'descSize' => 19, 'descLines' => 3,
            'metaFont' => 'swiss', 'metaSize' => 16, 'gap' => 28,
        ]);
        $padY = 26;
        $heights = array_map(fn ($r) => $padY * 2 + max($this->stackHeight($r, 8, 10), $r['price']['h']), $rows);

        $cta = $this->cta($d);
        $chipH = 52;
        $chipW = $cta ? $t->spacedWidth($cta, 'swissb', 16, 2.4) + 44 : 0;
        $chipTextTop = $this->centerTop($t, 'swissb', 16, $chipH / 2);
        $url = $d['url'] ? $t->block($d['url'], 'swissb', 27, $cw - $chipW - 24 - 200, 1, 16) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'swiss', 20, $cw, 1, 14) : null;
        $updated = $t->line($this->updated($d), 'swiss', 16);
        $more = $d['more'] > 0 ? $t->line('… và '.$d['more'].' dịch vụ khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'swiss', 20) : null;

        $y = $M;
        $topY = $y;
        $y += $topH + 14;
        $rule1 = $y;
        $y += $rule + 22;
        $blockY = $y;
        $y += $blockH + 26;
        $headY = null;
        if ($head) {
            $headY = $y;
            $y += $head['h'] + 34;
        }
        $secRule = $y;
        $secY = $y + $rule + 14;
        $y = $secY + $sec['h'] + 20;
        $listY = $y;
        $y += array_sum($heights);
        $moreY = null;
        if ($more) {
            $moreY = $y + 22;
            $y += 22 + $more['h'] + 4;
        }
        $bandY = $y + 44;
        $bandPad = 44;
        $by = $bandY + $bandPad;
        $by += ($cta ? $chipH : 0) + ($contact ? ($cta ? 20 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);
        $height = $by + $bandPad;

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $t, $d, $pal, $M, $cw, $rule, $top, $topY, $rule1, $blockW, $blockY, $blockH, $nx, $name, $tagBlock, $head, $headY, $sec, $count, $secRule, $secY,
            $idxW, $rows, $heights, $padY, $listY, $cta, $chipH, $chipW, $chipTextTop, $url, $contact, $updated, $more, $moreY, $bandY, $bandPad, $height
        ) {
            $c->fill('#ffffff');
            $c->spaced('BẢNG GIÁ', 'swissb', 14, 3.4, $M, $topY, '#000000');
            $c->spaced(sprintf('%02d / %d', $d['month'], $d['year']), 'swiss', 14, 3.4, $M + $cw, $topY, '#000000', 'r');
            $c->rect($M, $rule1, $cw, $rule, '#000000');

            // Khối màu nhấn: ảnh đại diện trên, khẩu hiệu dưới
            $c->rect($M, $blockY, $blockW, $blockH, $d['accent']);
            $c->avatar($d['avatar'], $d['initials'], $M + 24, $blockY + 24, 84, $pal['--on-accent'], $d['accent'], 'swissb', 3, $pal['--on-accent']);
            if ($tagBlock) {
                $c->text($tagBlock, $M + 24, $blockY + $blockH - 24 - $tagBlock['h'], $pal['--on-accent']);
            } else {
                $c->spaced('₫', 'swissb', 120, 0, $M + 24, $blockY + $blockH - 24 - 132, $pal['--on-accent'], 'l', 0.92);
            }
            $c->text($name, $nx, $blockY + $blockH - $name['h'] - 4, '#000000');

            if ($head) {
                $c->text($head, $M, $headY, '#000000');
            }

            $c->rect($M, $secRule, $cw, $rule, '#000000');
            $c->text($sec, $M, $secY, '#000000');
            $c->text($count, $M + $cw, $secY + $sec['h'] - $count['h'] - 6, '#595959', 'r');

            $ry = $listY;
            foreach ($rows as $i => $r) {
                if ($i > 0) {
                    $c->rect($M, $ry, $cw, $rule, '#000000');
                }
                $iy = $ry + $padY;
                $idx = $t->line(str_pad((string) $r['n'], 2, '0', STR_PAD_LEFT), 'swissb', 21);
                $c->text($idx, $M, $iy + $r['name']['asc'] - $idx['asc'], $pal['--accent-ink']);
                $this->paintStack($c, $r, $M + $idxW, $iy, ['name' => '#000000', 'desc' => '#1c1c1c', 'meta' => '#595959'], 8, 10);
                $c->text($r['price'], $M + $cw, $this->priceTop($r, $iy), $r['known'] ? '#000000' : '#595959', 'r');
                $ry += $heights[$i];
            }
            $c->rect($M, $ry, $cw, $rule, '#000000');

            if ($more) {
                $c->text($more, $M, $moreY, '#1c1c1c');
            }

            // Chân trang đen tràn mép
            $c->rect(0, $bandY, $c->w, $c->h - $bandY, '#000000');
            $fy = $bandY + $bandPad;
            if ($cta) {
                $c->rect($M, $fy, $chipW, $chipH, $d['accent']);
                $c->spaced($cta, 'swissb', 16, 2.4, $M + 22, $fy + $chipTextTop, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $M + $chipW + 24, $fy + ($chipH - $url['h']) / 2, '#ffffff');
                }
                $c->text($updated, $M + $cw, $fy + ($chipH - $updated['h']) / 2, '#a3a3a3', 'r');
                $fy += $chipH + 20;
            }
            if ($contact) {
                $c->text($contact, $M, $fy, '#e5e5e5');
                if (! $cta) {
                    $c->text($updated, $M + $cw, $fy + ($contact['h'] - $updated['h']) / 2, '#a3a3a3', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $M + $cw, $fy, '#a3a3a3', 'r');
            }
        }];
    }
}
