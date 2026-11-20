<?php

namespace App\Services\PriceList;

use App\Support\Color;
use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "Thẻ màu": như quạt màu Pantone — mỗi dịch vụ là một thẻ trắng có ô màu (sắc độ 100→20% của màu nhấn), kèm mã HEX thật. */
final class SwatchTheme extends Theme
{
    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $M = 56;
        $cw = self::W - 2 * $M;
        $hex = strtoupper($d['accent']);
        $tints = [1.0, 0.8, 0.6, 0.4, 0.2];

        // Đầu trang: thẻ lớn (ô màu + nhãn) và cột phải (câu chào + dải sắc độ)
        $chipW = 700;
        $colorH = 210;
        $nameBlk = $t->block($d['name'], 'swissb', 52, $chipW - 56, 2, 32, 0.92);
        $meta = $t->line('NÉT '.ltrim($hex, '#').' C · '.$d['slug'], 'mono', 14);
        $labelH = 26 + $nameBlk['h'] + 10 + $meta['h'] + 26;
        $chipH = $colorH + $labelH;
        $rx = $M + $chipW + 32;
        $rw = self::W - $M - $rx;
        $head = $d['headline'] !== '' ? $t->block($d['headline'], 'swiss', 27, $rw, 5, 20, 1.02) : null;
        $boxW = ($rw - 4 * 8) / 5;
        $rightH = ($head ? $head['h'] + 28 : 0) + 72;
        $headerH = max($chipH, $rightH);

        $sec = $t->line('BẢNG GIÁ', 'swissb', 30);
        $count = $t->line((count($d['services']) + $d['more']).' dịch vụ', 'mono', 15);

        $swW = 156;
        $rows = $this->rows($t, $d, $cw - $swW - 32 - 32, [
            'nameFont' => 'swissb', 'nameSize' => 30, 'nameMin' => 22, 'nameLines' => 3, 'nameLead' => 0.98,
            'priceFont' => 'monob', 'priceSize' => 27, 'priceMin' => 18, 'priceFontUnknown' => 'mono', 'priceSizeUnknown' => 19,
            'descFont' => 'body', 'descSize' => 19, 'descLines' => 3,
            'metaFont' => 'mono', 'metaSize' => 14, 'gap' => 28,
        ]);
        $padY = 26;
        $heights = array_map(fn ($r) => max(128, $padY * 2 + max($this->stackHeight($r, 8, 10), $r['price']['h'])), $rows);

        $cta = $this->cta($d);
        $btnH = 48;
        $btnW = $cta ? $t->spacedWidth($cta, 'swissb', 15, 2.2) + 44 : 0;
        $btnTextTop = $this->centerTop($t, 'swissb', 15, $btnH / 2);
        $url = $d['url'] ? $t->block($d['url'], 'mono', 21, $cw - 64 - $btnW - 24 - 190, 1, 15) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'mono', 17, $cw - 64, 1, 13) : null;
        $updated = $t->line($this->updated($d), 'mono', 14);
        $more = $d['more'] > 0 ? $t->line('+ '.$d['more'].' dịch vụ khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'mono', 16) : null;
        $footH = 28 * 2 + ($cta ? $btnH : 0) + ($contact ? ($cta ? 18 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);

        $y = $M;
        $headY = $y;
        $y += $headerH + 52;
        $secY = $y;
        $y += $sec['h'] + 24;
        $listY = $y;
        $y += array_sum($heights) + 20 * (count($rows) - 1);
        $moreY = null;
        if ($more) {
            $moreY = $y + 22;
            $y += 22 + $more['h'];
        }
        $footY = $y + 28;
        $height = $footY + $footH + $M;

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $t, $d, $pal, $M, $cw, $hex, $tints, $chipW, $colorH, $nameBlk, $meta, $labelH, $chipH, $rx, $rw, $head, $boxW, $sec, $count, $swW, $rows, $heights, $padY,
            $cta, $btnH, $btnW, $btnTextTop, $url, $contact, $updated, $more, $headY, $secY, $listY, $moreY, $footY, $footH
        ) {
            $ink = '#161618';
            $c->fill('#e9e9e6');

            // Thẻ lớn: ô màu nhấn trên, nhãn trắng dưới
            $c->shadow($M, $headY, $chipW, $chipH, 4, 18, 6, 0.12);
            $c->rrect($M, $headY, $chipW, $chipH, 4, '#ffffff');
            $c->rrect($M, $headY, $chipW, $colorH + 10, 4, $d['accent']);
            $c->rect($M, $headY + $colorH, $chipW, 10, '#ffffff');
            $c->avatar($d['avatar'], $d['initials'], $M + 24, $headY + 24, 84, $pal['--on-accent'], $d['accent'], 'swissb', 3, '#ffffff');
            $hexBlk = $t->line($hex, 'monob', 32);
            $c->text($hexBlk, $M + 24, $headY + $colorH - 24 - $hexBlk['h'], $pal['--on-accent'], 'l', 0.92);
            $c->text($nameBlk, $M + 28, $headY + $colorH + 26, $ink);
            $c->text($meta, $M + 28, $headY + $colorH + 26 + $nameBlk['h'] + 10, '#66666d');

            // Cột phải
            $ry = $headY;
            if ($head) {
                $c->text($head, $rx, $ry, '#38383d');
                $ry += $head['h'] + 28;
            }
            foreach ($tints as $i => $tint) {
                $col = Color::mix($d['accent'], '#ffffff', 1 - $tint);
                $bx = $rx + $i * ($boxW + 8);
                $c->shadow($bx, $ry, $boxW, 64, 3, 6, 2, 0.1);
                $c->rrect($bx, $ry, $boxW, 64, 3, $col);
                $lab = $t->line((string) round($tint * 100), 'mono', 13);
                $c->text($lab, $bx + 8, $ry + 64 - 8 - $lab['h'], Color::contrast($col, '#ffffff') >= 3 ? '#ffffff' : $ink);
            }

            // Tiêu đề mục
            $c->text($sec, $M, $secY, $ink);
            $c->text($count, $M + $cw, $secY + $sec['h'] - $count['h'] - 4, '#66666d', 'r');

            // Các thẻ dịch vụ
            $y = $listY;
            foreach ($rows as $i => $r) {
                $h = $heights[$i];
                $tint = $tints[$i % 5];
                $col = Color::mix($d['accent'], '#ffffff', 1 - $tint);
                $onCol = Color::contrast($col, '#ffffff') >= 3 ? '#ffffff' : $ink;
                $c->shadow($M, $y, $cw, $h, 4, 14, 4, 0.1);
                $c->rrect($M, $y, $cw, $h, 4, '#ffffff');
                $c->rrect($M, $y, $swW + 8, $h, 4, $col);
                $c->rect($M + $swW, $y, 10, $h, '#ffffff');
                $no = $t->line('№ '.str_pad((string) $r['n'], 2, '0', STR_PAD_LEFT), 'mono', 13);
                $c->text($no, $M + 16, $y + 14, $onCol, 'l', 0.9);
                $tl = $t->line(strtoupper(Color::mix($d['accent'], '#ffffff', 1 - $tint)), 'monob', 14);
                $c->text($tl, $M + 16, $y + $h - 14 - $tl['h'], $onCol, 'l', 0.95);

                $iy = $y + ($h - $this->stackHeight($r, 8, 10)) / 2;
                $this->paintStack($c, $r, $M + $swW + 32, $iy, ['name' => $ink, 'desc' => '#38383d', 'meta' => '#66666d'], 8, 10);
                $c->text($r['price'], $M + $cw - 32, $this->priceTop($r, $iy), $r['known'] ? $pal['--accent-ink'] : '#66666d', 'r');
                $y += $h + 20;
            }

            if ($more) {
                $c->text($more, $M, $moreY, '#66666d');
            }

            // Thẻ chân trang
            $c->shadow($M, $footY, $cw, $footH, 4, 14, 4, 0.1);
            $c->rrect($M, $footY, $cw, $footH, 4, '#ffffff');
            $fx = $M + 32;
            $fy = $footY + 28;
            if ($cta) {
                $c->rrect($fx, $fy, $btnW, $btnH, 4, $d['accent']);
                $c->spaced($cta, 'swissb', 15, 2.2, $fx + 22, $fy + $btnTextTop, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $fx + $btnW + 24, $fy + ($btnH - $url['h']) / 2, $ink);
                }
                $c->text($updated, $M + $cw - 32, $fy + ($btnH - $updated['h']) / 2, '#66666d', 'r');
                $fy += $btnH + 18;
            }
            if ($contact) {
                $c->text($contact, $fx, $fy, '#38383d');
                if (! $cta) {
                    $c->text($updated, $M + $cw - 32, $fy + ($contact['h'] - $updated['h']) / 2, '#66666d', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $M + $cw - 32, $fy, '#66666d', 'r');
            }
        }];
    }
}
