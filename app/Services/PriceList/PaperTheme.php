<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "Tối giản": nền giấy xám, một khung trắng bo góc, đường kẻ mảnh, giá in màu nhấn. */
final class PaperTheme extends Theme
{
    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $M = 56;
        $cw = self::W - 2 * $M;
        $padX = 36;
        $padY = 28;

        // Đầu trang
        $avatar = 88;
        $tx = $M + $avatar + 28;
        $tw = self::W - $M - $tx;
        $label = ['str' => 'BẢNG GIÁ', 'font' => 'bodyb', 'size' => 15];
        $labelH = $t->metrics('bodyb', 15)['asc'] + $t->metrics('bodyb', 15)['desc'];
        $name = $t->block($d['name'], 'bodyb', 46, $tw, 2, 30, 0.98);
        $head = $d['headline'] !== '' ? $t->block($d['headline'], 'body', 22, $tw, 2, 18, 1.0) : null;
        $stackH = $labelH + 10 + $name['h'] + ($head ? 10 + $head['h'] : 0);
        $headerH = max($avatar, $stackH);

        // Bảng giá
        $rows = $this->rows($t, $d, $cw - 2 * $padX, [
            'nameFont' => 'bodyb', 'nameSize' => 28, 'nameMin' => 22, 'nameLines' => 2,
            'priceFont' => 'bodyb', 'priceSize' => 28, 'priceMin' => 20, 'priceFontUnknown' => 'body', 'priceSizeUnknown' => 21,
            'descFont' => 'body', 'descSize' => 19, 'descLines' => 3,
            'metaFont' => 'body', 'metaSize' => 16, 'gap' => 32,
        ]);
        $heights = array_map(fn ($r) => $padY * 2 + max($this->stackHeight($r, 8, 10), $r['price']['h']), $rows);
        $cardH = array_sum($heights);

        // Chân trang
        $cta = $this->cta($d);
        $chipH = 52;
        $chipLabelW = $cta ? $t->spacedWidth($cta, 'bodyb', 16, 2.4) : 0;
        $chipW = $chipLabelW + 48;
        $chipTextTop = $this->centerTop($t, 'bodyb', 16, $chipH / 2);
        $url = $d['url'] ? $t->block($d['url'], 'body', 23, $cw - $chipW - 24, 1, 16) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'body', 20, $cw - 200, 1, 15) : null;
        $updated = $t->line($this->updated($d), 'body', 18);
        $more = $d['more'] > 0 ? $t->line('… và '.$d['more'].' dịch vụ khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'body', 20) : null;

        $y = $M + $headerH + 40;
        $cardY = $y;
        $y += $cardH;
        $moreY = $more ? $y + 22 : null;
        if ($more) {
            $y += 22 + $more['h'];
        }
        $y += 40;
        $footY = $y;
        $y += ($cta ? $chipH : 0) + ($contact ? ($cta ? 20 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);
        $height = $y + $M;

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $d, $pal, $M, $cw, $padX, $padY, $avatar, $tx, $name, $head, $label, $labelH, $rows, $heights, $cardY, $cardH,
            $cta, $chipH, $chipW, $chipTextTop, $url, $contact, $updated, $more, $moreY, $footY
        ) {
            $c->fill('#f4f4f2');

            $c->avatar($d['avatar'], $d['initials'], $M, $M, $avatar, $d['accent'], $pal['--on-accent'], 'bodyb', 3, '#ffffff');
            $y = $M;
            $c->spaced($label['str'], $label['font'], $label['size'], 3.2, $tx, $y, $pal['--accent-ink']);
            $y += $labelH + 10;
            $c->text($name, $tx, $y, '#1d1d22');
            $y += $name['h'];
            if ($head) {
                $c->text($head, $tx, $y + 10, '#6d6d75');
            }

            $c->rrectBordered($M, $cardY, $cw, $cardH, 14, 1.5, '#e3e3df', '#ffffff');
            $ry = $cardY;
            foreach ($rows as $i => $r) {
                if ($i > 0) {
                    $c->rect($M + 1.5, $ry, $cw - 3, 1.5, '#e3e3df');
                }
                $iy = $ry + $padY;
                $this->paintStack($c, $r, $M + $padX, $iy, ['name' => '#1d1d22', 'desc' => '#6d6d75', 'meta' => '#6d6d75'], 8, 10);
                $c->text($r['price'], $M + $cw - $padX, $this->priceTop($r, $iy), $r['known'] ? $pal['--accent-ink'] : '#6d6d75', 'r');
                $ry += $heights[$i];
            }

            if ($more) {
                $c->text($more, self::W / 2, $moreY, '#6d6d75', 'c');
            }

            $fy = $footY;
            if ($cta) {
                $c->rrect($M, $fy, $chipW, $chipH, 10, $d['accent']);
                $c->spaced($cta, 'bodyb', 16, 2.4, $M + 24, $fy + $chipTextTop, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $M + $chipW + 24, $fy + ($chipH - $url['h']) / 2, '#3f3f46');
                }
                $c->text($updated, $M + $cw, $fy + ($chipH - $updated['h']) / 2, '#6d6d75', 'r');
                $fy += $chipH + 20;
            }
            if ($contact) {
                $c->text($contact, $M, $fy, '#3f3f46');
                if (! $cta) {
                    $c->text($updated, $M + $cw, $fy + ($contact['h'] - $updated['h']) / 2, '#6d6d75', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $M + $cw, $fy, '#6d6d75', 'r');
            }
        }];
    }

}
