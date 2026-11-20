<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/** Mẫu "Bàn vẽ": như đang mở file trong phần mềm thiết kế — nền tối chấm lưới, thước kẻ, artboard trắng, khung chọn có 4 núm. */
final class ArtboardTheme extends Theme
{
    public function build(GdText $t, array $d): array
    {
        $pal = $d['pal'];
        $ax = 60;
        $aw = self::W - 2 * $ax;
        $ay = 92;
        $P = 56;
        $inner = $aw - 2 * $P;
        $handle = 9;

        // Đầu artboard: tên studio trong khung chọn
        $name = $t->block($d['name'], 'grotb', 70, $inner - 28, 2, 40, 0.96);
        $boxW = $name['w'] + 28;
        $boxH = $name['h'] + 20;
        $dim = $t->line(round($boxW).' × '.round($boxH), 'mono', 13);
        $dimW = $dim['w'] + 16;
        $head = $d['headline'] !== '' ? $t->block($d['headline'], 'grot', 27, $inner, 2, 20, 1.0) : null;
        $sec = $t->line('Bảng giá', 'grotb', 32);
        $count = $t->line(count($d['services']) + $d['more'].' dịch vụ', 'mono', 15);

        $rows = $this->rows($t, $d, $inner - 64, [
            'nameFont' => 'grotb', 'nameSize' => 29, 'nameMin' => 22, 'nameLines' => 2,
            'priceFont' => 'monob', 'priceSize' => 26, 'priceMin' => 18, 'priceFontUnknown' => 'mono', 'priceSizeUnknown' => 19,
            'descFont' => 'body', 'descSize' => 19, 'descLines' => 3,
            'metaFont' => 'mono', 'metaSize' => 15, 'gap' => 28,
        ]);
        $pad = 26;
        $heights = array_map(fn ($r) => $pad * 2 + max($this->stackHeight($r, 8, 10), $r['price']['h']), $rows);

        $cta = $this->cta($d);
        $chipH = 48;
        $chipW = $cta ? $t->spacedWidth($cta, 'grotb', 15, 2.2) + 44 : 0;
        $chipTextTop = $this->centerTop($t, 'grotb', 15, $chipH / 2);
        $url = $d['url'] ? $t->block($d['url'], 'mono', 21, $inner - $chipW - 24 - 190, 1, 15) : null;
        $contact = $d['contact'] ? $t->block($this->contactLine($d), 'mono', 17, $inner, 1, 13) : null;
        $updated = $t->line($this->updated($d), 'mono', 15);
        $more = $d['more'] > 0 ? $t->line('+ '.$d['more'].' dịch vụ khác'.($d['url'] ? ' — xem đầy đủ tại '.$d['url'] : ''), 'mono', 16) : null;

        // Dọc: artboard
        $y = $P;
        $nameTop = $y + 10;
        $y += $boxH + 12 + 22 + 22;
        $headTop = $y;
        if ($head) {
            $y += $head['h'] + 40;
        }
        $secTop = $y;
        $y += $sec['h'] + 18;
        $listTop = $y;
        $y += array_sum($heights);
        $moreTop = null;
        if ($more) {
            $moreTop = $y + 20;
            $y += 20 + $more['h'] + 22;
        }
        $footTop = $y + 32;
        $y = $footTop + ($cta ? $chipH : 0) + ($contact ? ($cta ? 18 : 0) + $contact['h'] : 0) + (! $cta && ! $contact ? $updated['h'] : 0);
        $artH = $y + $P;
        $height = $ay + $artH + 64;

        return ['height' => $height, 'paint' => function (GdCanvas $c) use (
            $t, $d, $pal, $ax, $aw, $ay, $P, $inner, $handle, $name, $boxW, $boxH, $dim, $dimW, $head, $sec, $count, $rows, $pad, $heights,
            $cta, $chipH, $chipW, $chipTextTop, $url, $contact, $updated, $more, $artH, $nameTop, $headTop, $secTop, $listTop, $moreTop, $footTop
        ) {
            // Nền tối + chấm lưới
            $c->fill('#2c2c30');
            for ($gy = 20; $gy < $c->h; $gy += 20) {
                for ($gx = 20; $gx < $c->w; $gx += 20) {
                    $c->rect($gx, $gy, 1, 1, '#404046');
                }
            }

            // Nhãn trên artboard
            $lab = $t->block('Bảng giá — '.$d['name'], 'mono', 15, $aw - 160, 1, 12);
            $c->text($lab, $ax, $ay - 12 - $lab['h'], '#a0a0a8');
            $size = $t->line(Theme::W - 2 * $ax.' × '.round($artH), 'mono', 15);
            $c->text($size, $ax + $aw, $ay - 12 - $size['h'], '#a0a0a8', 'r');

            // Artboard
            $c->shadow($ax, $ay, $aw, $artH, 4, 26, 10, 0.55);
            $c->rrect($ax, $ay, $aw, $artH, 4, '#ffffff');
            $x = $ax + $P;
            $top = $ay;

            // Tên trong khung chọn
            $c->text($name, $x + 14, $top + $nameTop, '#18181b');
            $bx = $x + 14 - 14;
            $by = $top + $nameTop - 10;
            $c->frame($bx, $by, $boxW, $boxH, 1.6, $d['accent']);
            foreach ([[$bx, $by], [$bx + $boxW, $by], [$bx, $by + $boxH], [$bx + $boxW, $by + $boxH]] as [$hx, $hy]) {
                $c->rect($hx - $handle / 2, $hy - $handle / 2, $handle, $handle, $d['accent']);
                $c->rect($hx - $handle / 2 + 1.6, $hy - $handle / 2 + 1.6, $handle - 3.2, $handle - 3.2, '#ffffff');
            }
            $c->rect($bx + $boxW / 2 - $dimW / 2, $by + $boxH + 12, $dimW, 22, $d['accent']);
            $c->text($dim, $bx + $boxW / 2, $by + $boxH + 12 + (22 - $dim['h']) / 2, $pal['--on-accent'], 'c');

            if ($head) {
                $c->text($head, $x, $top + $headTop, '#52525b');
            }

            // Danh sách "layer"
            $c->text($sec, $x, $top + $secTop, '#18181b');
            $c->text($count, $x + $inner, $top + $secTop + $sec['h'] - $count['h'] - 4, '#8b8b93', 'r');
            $ry = $top + $listTop;
            $c->rect($x, $ry, $inner, 1.5, '#18181b');
            foreach ($rows as $i => $r) {
                if ($i > 0) {
                    $c->rect($x, $ry, $inner, 1, '#e4e4e7');
                }
                $iy = $ry + $pad;
                $idx = $t->line(str_pad((string) $r['n'], 2, '0', STR_PAD_LEFT), 'mono', 15);
                $c->text($idx, $x, $iy + $r['name']['asc'] - $idx['asc'], '#a1a1aa');
                $this->paintStack($c, $r, $x + 64, $iy, ['name' => '#18181b', 'desc' => '#6b6b73', 'meta' => '#8b8b93'], 8, 10);
                $c->text($r['price'], $x + $inner, $this->priceTop($r, $iy), $r['known'] ? $pal['--accent-ink'] : '#6b6b73', 'r');
                $ry += $heights[$i];
            }
            $c->rect($x, $ry - 1, $inner, 1, '#e4e4e7');

            if ($more) {
                $c->text($more, $x, $top + $moreTop, '#8b8b93');
            }

            $fy = $top + $footTop;
            if ($cta) {
                $c->rrect($x, $fy, $chipW, $chipH, 6, $d['accent']);
                $c->spaced($cta, 'grotb', 15, 2.2, $x + 22, $fy + $chipTextTop, $pal['--on-accent']);
                if ($url) {
                    $c->text($url, $x + $chipW + 24, $fy + ($chipH - $url['h']) / 2, '#18181b');
                }
                $c->text($updated, $x + $inner, $fy + ($chipH - $updated['h']) / 2, '#8b8b93', 'r');
                $fy += $chipH + 18;
            }
            if ($contact) {
                $c->text($contact, $x, $fy, '#52525b');
                if (! $cta) {
                    $c->text($updated, $x + $inner, $fy + ($contact['h'] - $updated['h']) / 2, '#8b8b93', 'r');
                }
            } elseif (! $cta) {
                $c->text($updated, $x + $inner, $fy, '#8b8b93', 'r');
            }

            // Thước kẻ (vẽ sau cùng để nằm trên nền)
            $c->rect(0, 0, $c->w, 20, '#1f1f22');
            $c->rect(0, 20, $c->w, 1, '#111113');
            $c->rect(0, 0, 20, $c->h, '#1f1f22');
            $c->rect(20, 0, 1, $c->h, '#111113');
            for ($tx = 20; $tx < $c->w; $tx += 10) {
                $big = (($tx - 20) % 100) === 0;
                $c->rect($tx, 20 - ($big ? 12 : 5), 1, $big ? 12 : 5, $big ? '#8b8b93' : '#55555c');
            }
            for ($ty = 20; $ty < $c->h; $ty += 10) {
                $big = (($ty - 20) % 100) === 0;
                $c->rect(20 - ($big ? 12 : 5), $ty, $big ? 12 : 5, 1, $big ? '#8b8b93' : '#55555c');
            }
            $c->rect(0, 0, 20, 20, '#1f1f22');
        }];
    }
}
