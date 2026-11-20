<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "In Risograph": hai màu mực in chồng, hạt giấy, khung viền đậm với bóng cứng lệch khuôn, giá in trên nhãn màu. */
final class RisoTheme extends Theme
{
    private const INK = '#201e2c';

    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $M = 56;
        $cw = self::W - 2 * $M;
        $shadow = 6;
        $cardW = $cw - $shadow;
        $padX = 34;
        $padY = 28;

        $avatar = 84;
        $tx = $M + $avatar + 28;
        $tw = self::W - $M - $tx;
        $tag = $t->line('BẢNG GIÁ', 'wide', 13);
        $tagW = $t->spacedWidth('BẢNG GIÁ', 'wide', 13, 2.4) + 28;
        $name = $t->block($d['name'], 'wide', 52, $tw - 6, 2, 28, 0.98);
        // Câu chào: từng dòng nằm trên 1 dải mực xanh
        $headLines = [];
        if ($d['headline'] !== '') {
            $hb = $t->block($d['headline'], 'wide', 18, $tw - 24, 2, 14, 1.0);
            foreach ($hb['lines'] as $line) {
                $headLines[] = $t->line($line, 'wide', $hb['size']);
            }
        }
        $headLineH = $headLines ? $headLines[0]['h'] + 14 : 0;
        $stackH = $tag['h'] + 14 + 6 + $name['h'] + ($headLines ? 18 + count($headLines) * $headLineH : 0);
        $headerH = max($avatar, $stackH);

        $rows = $this->rows($t, $d, $cardW - 2 * $padX, [
            'nameFont' => 'wide', 'nameSize' => 22, 'nameMin' => 16, 'nameLines' => 2, 'nameLead' => 1.12,
            'priceFont' => 'wide', 'priceSize' => 19, 'priceMin' => 14, 'priceFontUnknown' => 'bodyb', 'priceSizeUnknown' => 19,
            'priceExtra' => 40, 'priceMaxShare' => 0.4,
            'descFont' => 'body', 'descSize' => 19, 'descLines' => 3,
            'metaFont' => 'bodyb', 'metaSize' => 15, 'gap' => 28,
        ]);
        $pillH = 0;
        foreach ($rows as $r) {
            $pillH = max($pillH, $r['price']['h'] + 22);
        }
        $heights = array_map(fn ($r) => $padY * 2 + max($this->stackHeight($r, 10, 10), $r['known'] ? $r['price']['h'] + 22 : $r['price']['h']), $rows);
        $cardH = array_sum($heights) + 2 * (count($rows) - 1) * 0 + 4;

        $cta = $this->cta($d);
        $chipH = 54;
        $chipW = $cta ? $t->spacedWidth($cta, 'wide', 14, 1.8) + 52 : 0;
        $chipTextTop = $this->centerTop($t, 'wide', 14, $chipH / 2);
        $url = $d['url'] ? $t->block($d['url'], 'bodyb', 22, $cw - $chipW - 40 - 190, 1, 15) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'body', 20, $cw, 1, 15) : null;
        $updated = $t->line($this->updated($d), 'bodyb', 17);
        $more = $d['more'] > 0 ? $t->line('… và '.$d['more'].' dịch vụ khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'bodyb', 19) : null;

        $y = $M + $headerH + 28;
        $ruleY = $y;
        $cardY = $y + 2 + 36;
        $y = $cardY + $cardH + $shadow;
        $moreY = null;
        if ($more) {
            $moreY = $y + 22;
            $y += 22 + $more['h'];
        }
        $footY = $y + 40;
        $y = $footY + ($cta ? $chipH + 3 : 0) + ($contact ? ($cta ? 20 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);
        $height = $y + $M;
        $seed = crc32($d['name'].$d['slug'] ?? '');

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $t, $d, $pal, $M, $cw, $shadow, $cardW, $padX, $padY, $avatar, $tx, $tag, $tagW, $name, $headLines, $headLineH, $rows, $heights, $ruleY, $cardY, $cardH,
            $cta, $chipH, $chipW, $chipTextTop, $url, $contact, $updated, $more, $moreY, $footY, $seed
        ) {
            $ink = self::INK;
            $c->fill('#f1efe9');
            $c->grain(0, 0, $c->w, $c->h, $seed, 0.014, $ink, 0.16);

            // Đầu trang
            $c->avatar($d['avatar'], $d['initials'], $M, $M, $avatar, $d['accent'], $pal['--on-accent'], 'wide', 3, $ink);
            $y = $M;
            $c->rrect($tx, $y, $tagW, $tag['h'] + 10, ($tag['h'] + 10) / 2, $ink);
            $c->spaced('BẢNG GIÁ', 'wide', 13, 2.4, $tx + 14, $y + 5, '#f1efe9');
            $y += $tag['h'] + 10 + 14;
            // Tên: lớp bóng (mực 2) lệch khuôn rồi lớp mặt (mực 1)
            $off = max(2.5, $name['size'] * 0.05);
            $c->text($name, $tx + $off, $y + $off, $pal['--riso-shadow']);
            $c->text($name, $tx, $y, $pal['--riso-face']);
            $y += $name['h'] + 18 + 4;
            foreach ($headLines as $i => $hl) {
                $by = $y + $i * $headLineH;
                $c->rect($tx, $by, $hl['w'] + 24, $headLineH, $pal['--ink-riso']);
                $c->text($hl, $tx + 12, $by + 7, $pal['--on-riso']);
            }

            $c->rect($M, $ruleY, $cw, 2, $ink);

            // Khung bảng giá: bóng cứng lệch khuôn (mực xanh) + viền đậm
            $c->rrect($M + $shadow, $cardY + $shadow, $cardW, $cardH, 18, $pal['--ink-riso']);
            $c->rrectBordered($M, $cardY, $cardW, $cardH, 18, 2, $ink, '#fbfaf6');
            $ry = $cardY + 2;
            foreach ($rows as $i => $r) {
                if ($i > 0) {
                    $c->rect($M + 2, $ry - 1, $cardW - 4, 2, $ink);
                }
                $iy = $ry + $padY;
                $this->paintStack($c, $r, $M + $padX, $iy, ['name' => $ink, 'desc' => '#3b3948', 'meta' => '#625f6d'], 10, 10);
                $right = $M + $cardW - $padX;
                if ($r['known']) {
                    $pw = $r['price']['w'] + 40;
                    $ph = $r['price']['h'] + 22;
                    $py = $iy + $r['name']['asc'] - $r['price']['asc'] - 11 + ($r['name']['asc'] - $r['name']['cap']) * 0 - 4;
                    $px = $right - $pw;
                    $c->rrect($px + 3, $py + 3, $pw, $ph, $ph / 2, $ink);
                    $c->rrectBordered($px, $py, $pw, $ph, $ph / 2, 2, $ink, $d['accent']);
                    $c->text($r['price'], $px + 20, $py + 11, $pal['--on-accent']);
                } else {
                    $c->text($r['price'], $right, $this->priceTop($r, $iy), '#625f6d', 'r');
                }
                $ry += $heights[$i];
            }

            if ($more) {
                $c->text($more, $M, $moreY, $ink);
            }

            $fy = $footY;
            if ($cta) {
                $c->rrect($M + 3, $fy + 3, $chipW, $chipH, $chipH / 2, $ink);
                $c->rrectBordered($M, $fy, $chipW, $chipH, $chipH / 2, 2, $ink, $d['accent']);
                $c->spaced($cta, 'wide', 14, 1.8, $M + 26, $fy + $chipTextTop, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $M + $chipW + 28, $fy + ($chipH - $url['h']) / 2, $ink);
                }
                $c->text($updated, $M + $cw, $fy + ($chipH - $updated['h']) / 2, '#625f6d', 'r');
                $fy += $chipH + 3 + 20;
            }
            if ($contact) {
                $c->text($contact, $M, $fy, '#3b3948');
                if (! $cta) {
                    $c->text($updated, $M + $cw, $fy + ($contact['h'] - $updated['h']) / 2, '#625f6d', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $M + $cw, $fy, '#625f6d', 'r');
            }
        }];
    }
}
