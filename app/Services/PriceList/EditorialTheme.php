<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "Tạp chí": măng-sét, tên studio chữ có chân cực lớn, câu chào in nghiêng, bảng giá như thực đơn có đường chấm dẫn tới giá. */
final class EditorialTheme extends Theme
{
    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $M = 64;
        $cw = self::W - 2 * $M;
        $ink = '#1c1a16';

        // Măng-sét
        $mast = $t->line('X', 'bodyb', 13);
        $mastPad = 12;
        $mastH = $mastPad * 2 + $mast['h'];
        $mastL = mb_strtoupper($d['tagline'] !== '' ? $d['tagline'] : 'Studio thiết kế');
        $mastL = $t->block($mastL, 'bodyb', 13, $cw * 0.34, 1, 11)['lines'][0];
        $mastC = sprintf('SỐ %02d · %d', $d['month'], $d['year']);
        $mastR = '@'.$d['slug'];

        $name = $t->block($d['name'], 'serifb', 128, $cw, 2, 60, 0.92);
        $head = $d['headline'] !== '' ? $t->block($d['headline'], 'serifi', 34, $cw * 0.86, 2, 24, 1.0) : null;
        $by = $t->block('Bảng giá dịch vụ · '.(count($d['services']) + $d['more']).' mục', 'bodyb', 14, $cw - 60, 1, 12);

        $rows = $this->rows($t, $d, $cw, [
            'nameFont' => 'serifb', 'nameSize' => 37, 'nameMin' => 26, 'nameLines' => 3, 'nameLead' => 0.98,
            'priceFont' => 'serifb', 'priceSize' => 37, 'priceMin' => 24, 'priceFontUnknown' => 'serifi', 'priceSizeUnknown' => 26,
            'descFont' => 'serifi', 'descSize' => 22, 'descLines' => 3, 'descWidth' => $cw * 0.8,
            'metaFont' => 'bodyb', 'metaSize' => 14, 'gap' => 56, 'priceMaxShare' => 0.34,
        ]);
        $label = $t->line('MỤC 00', 'bodyb', 13);
        $padY = 28;
        $heights = [];
        foreach ($rows as $k => $r) {
            // Dòng phụ viết hoa như chú thích tạp chí
            if ($r['meta']) {
                $rows[$k]['meta'] = $t->block(mb_strtoupper(implode(' ', $r['meta']['lines'])), 'bodyb', 14, $cw, 1, 11);
            }
            $heights[] = $padY * 2 + $label['h'] + 10 + $this->stackHeight($rows[$k], 10, 12);
        }

        $cta = $this->cta($d);
        $chipH = 46;
        $chipW = $cta ? $t->spacedWidth($cta, 'bodyb', 14, 2.4) + 40 : 0;
        $chipTextTop = $this->centerTop($t, 'bodyb', 14, $chipH / 2);
        $url = $d['url'] ? $t->block($d['url'], 'serifi', 27, $cw - $chipW - 24 - 200, 1, 18) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'serif', 21, $cw, 1, 15) : null;
        $updated = $t->line($this->updated($d), 'serifi', 19);
        $more = $d['more'] > 0 ? $t->line('… và '.$d['more'].' mục khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'serifi', 22) : null;

        $y = $M;
        $mastY = $y;
        $y += $mastH + 1 + 40;
        $nameY = $y;
        $y += $name['h'] + 22;
        $headY = $y;
        if ($head) {
            $y += $head['h'] + 22;
        }
        $byY = $y + 6;
        $avatar = 40;
        $y = $byY + max($avatar, $by['h']) + 26;
        $ruleY = $y;
        $y += 6;
        $listY = $y;
        $y += array_sum($heights);
        $moreY = null;
        if ($more) {
            $moreY = $y + 22;
            $y += 22 + $more['h'] + 6;
        }
        $footRule = $y + 34;
        $fy0 = $footRule + 6 + 30;
        $y = $fy0 + ($cta ? $chipH : 0) + ($contact ? ($cta ? 20 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);
        $height = $y + $M;

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $t, $d, $pal, $M, $cw, $ink, $mastPad, $mastH, $mastL, $mastC, $mastR, $mastY, $name, $nameY, $head, $headY, $by, $byY, $avatar, $rows, $heights, $label,
            $padY, $ruleY, $listY, $cta, $chipH, $chipW, $chipTextTop, $url, $contact, $updated, $more, $moreY, $footRule, $fy0
        ) {
            $c->fill('#f5f0e6');

            // Măng-sét
            $c->rect($M, $mastY, $cw, 1.5, $ink);
            $c->spaced($mastL, 'bodyb', 13, 2.3, $M, $mastY + 1.5 + $mastPad, $ink);
            $c->spaced($mastC, 'bodyb', 13, 2.3, $M + $cw / 2, $mastY + 1.5 + $mastPad, $ink, 'c');
            $c->spaced($mastR, 'bodyb', 13, 2.3, $M + $cw, $mastY + 1.5 + $mastPad, $ink, 'r');
            $c->rect($M, $mastY + $mastH, $cw, 1.5, $ink);

            $c->text($name, $M - 4, $nameY, $ink);
            if ($head) {
                $c->text($head, $M, $headY, '#3b3830');
            }
            $c->avatar($d['avatar'], $d['initials'], $M, $byY, $avatar, $d['accent'], $pal['--on-accent'], 'bodyb', 0);
            $c->text($by, $M + $avatar + 16, $byY + ($avatar - $by['h']) / 2, '#6b665b');

            // Vạch kép
            $c->rect($M, $ruleY, $cw, 1.5, $ink);
            $c->rect($M, $ruleY + 4, $cw, 1.5, $ink);

            $ry = $listY;
            foreach ($rows as $i => $r) {
                if ($i > 0) {
                    $c->rect($M, $ry, $cw, 1, '#d8cfbe');
                }
                $iy = $ry + $padY;
                $c->spaced('MỤC '.str_pad((string) $r['n'], 2, '0', STR_PAD_LEFT), 'bodyb', 13, 2.4, $M, $iy, $pal['--accent-ink']);
                $ny = $iy + $label['h'] + 10;
                $this->paintStack($c, $r, $M, $ny, ['name' => $ink, 'desc' => '#3b3830', 'meta' => '#6b665b'], 10, 12);

                // Giá cùng đường cơ sở với dòng cuối của tên, nối bằng đường chấm
                $lastTop = $ny + ($r['name']['lead'] * (count($r['name']['lines']) - 1));
                $base = $lastTop + $r['name']['asc'];
                $ptop = $base - $r['price']['asc'];
                $c->text($r['price'], $M + $cw, $ptop, $r['known'] ? $ink : '#6b665b', 'r');
                $lastW = $t->width(end($r['name']['lines']), $r['name']['font'], $r['name']['size']);
                $x1 = $M + $lastW + 16;
                $x2 = $M + $cw - $r['price']['w'] - 16;
                if ($x2 - $x1 > 24) {
                    $c->dotted($x1, $x2, $base - 5, 2.4, 6.6, $ink, 0.55);
                }
                $ry += $heights[$i];
            }

            if ($more) {
                $c->text($more, $M, $moreY, '#3b3830');
            }

            $c->rect($M, $footRule, $cw, 1.5, $ink);
            $c->rect($M, $footRule + 4, $cw, 1.5, $ink);
            $fy = $fy0;
            if ($cta) {
                $c->rect($M, $fy, $chipW, $chipH, $d['accent']);
                $c->spaced($cta, 'bodyb', 14, 2.4, $M + 20, $fy + $chipTextTop, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $M + $chipW + 24, $fy + ($chipH - $url['h']) / 2, $ink);
                }
                $c->text($updated, $M + $cw, $fy + ($chipH - $updated['h']) / 2, '#6b665b', 'r');
                $fy += $chipH + 20;
            }
            if ($contact) {
                $c->text($contact, $M, $fy, '#3b3830');
                if (! $cta) {
                    $c->text($updated, $M + $cw, $fy + ($contact['h'] - $updated['h']) / 2, '#6b665b', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $M + $cw, $fy, '#6b665b', 'r');
            }
        }];
    }
}
