<?php

namespace App\Services;

use App\Models\Studio;
use App\Support\Color;
use Illuminate\Support\Facades\Storage;

/**
 * Ảnh xem trước khi chia sẻ link trang đặt đơn (og:image) lên Facebook, Messenger, Zalo, Threads, X…
 *
 * Vì sao không dùng thẳng ảnh bìa: ảnh trên Nét lưu WebP (một số app chat không hiện), tỉ lệ tuỳ ý (bị cắt
 * mất tên/chủ thể), và không có chữ. Ảnh chia sẻ là JPEG 1200×630 (chuẩn 1.91:1 mọi nền tảng đều hiện
 * khung lớn), có tên studio, câu chào và địa chỉ đặt đơn — nhìn là biết của ai, bấm vào để làm gì.
 *
 *  - Có ảnh bìa: ảnh bìa tràn khung, phủ dải tối phía dưới, chữ trắng.
 *  - Chưa có ảnh bìa: nền giấy, chữ bên trái; bên phải ghép tối đa 3 tác phẩm tiêu biểu
 *    (chưa có tác phẩm thì là khối màu nhấn với chữ viết tắt của studio).
 *
 * Ảnh được tạo 1 lần rồi lưu ở storage/app/public/studios/{id}/og/{mã}.jpg. Mã đổi khi tên, câu chào,
 * màu nhấn, ảnh bìa, ảnh đại diện hoặc tác phẩm đổi → link ảnh đổi theo (?v=mã), Facebook/Zalo không
 * giữ ảnh cũ trong bộ nhớ đệm của họ.
 */
class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Tăng khi đổi bố cục ảnh để mọi studio được tạo lại ảnh mới. */
    private const VERSION = 1;

    private const PAD = 64;

    /** Mã nhận diện nội dung ảnh (dùng cho tên file + ?v= trên link). */
    public function fingerprint(Studio $studio): string
    {
        return substr(sha1(json_encode([
            self::VERSION,
            $studio->name,
            $this->headline($studio),
            $studio->accentHex(),
            $studio->cover_path,
            $studio->avatar_path,
            $studio->slug,
            $this->host(),
            $studio->cover_path ? [] : $studio->portfolioItems()->limit(3)->pluck('path')->all(),
        ])), 0, 16);
    }

    /**
     * Đường dẫn (trên disk "public") của ảnh chia sẻ — tạo mới nếu chưa có. Null khi máy chủ không vẽ được
     * (thiếu GD) và studio không có ảnh bìa → dùng ảnh mặc định của Nét.
     */
    public function path(Studio $studio): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $disk = Storage::disk('public');
        $dir = $studio->storageDir('og');
        $path = $dir.'/'.$this->fingerprint($studio).'.jpg';

        if ($disk->exists($path)) {
            return $path;
        }

        $image = $this->render($studio);
        if (! $image) {
            return null;
        }

        imageinterlace($image, true);
        ob_start();
        imagejpeg($image, null, 86);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        // Chỉ giữ 1 ảnh mới nhất cho mỗi studio.
        foreach ($disk->files($dir) as $old) {
            $disk->delete($old);
        }
        $disk->put($path, $jpeg);

        return $path;
    }

    /** Câu hiển thị dưới tên studio: câu chào lớn → khẩu hiệu → mặc định. */
    public function headline(Studio $studio): string
    {
        $text = trim((string) ($studio->page_headline ?: $studio->tagline));

        return $text !== '' ? $text : 'Đặt thiết kế trực tuyến — gửi brief, xem phương án, góp ý ngay trên một đường link.';
    }

    private function host(): string
    {
        return (string) parse_url(url('/'), PHP_URL_HOST);
    }

    private function render(Studio $studio): ?\GdImage
    {
        $canText = function_exists('imagettftext') && is_file($this->font('bold'));
        $cover = $studio->cover_path ? $this->load($studio->cover_path) : null;

        if (! $canText && ! $cover) {
            return null;
        }

        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($img, true);
        imagesavealpha($img, false);

        $cover
            ? $this->renderWithCover($img, $studio, $cover, $canText)
            : $this->renderPaper($img, $studio);

        return $img;
    }

    // ------------------------------------------------------------------ Bố cục 1: có ảnh bìa

    private function renderWithCover(\GdImage $img, Studio $studio, \GdImage $cover, bool $canText): void
    {
        $this->fillCover($img, $cover, 0, 0, self::WIDTH, self::HEIGHT);
        imagedestroy($cover);

        if (! $canText) {
            return;
        }

        // Dải tối phía dưới để chữ trắng luôn đọc được, dù ảnh bìa sáng hay tối.
        for ($y = 120; $y < self::HEIGHT; $y++) {
            $t = ($y - 120) / (self::HEIGHT - 120);
            $alpha = (int) round(127 - 127 * 0.84 * (3 * $t ** 2 - 2 * $t ** 3) ** 0.8);
            imagefilledrectangle($img, 0, $y, self::WIDTH, $y, imagecolorallocatealpha($img, 12, 12, 16, $alpha));
        }

        $pad = self::PAD;
        $maxW = self::WIDTH - $pad * 2;
        $white = imagecolorallocate($img, 255, 255, 255);
        $soft = imagecolorallocatealpha($img, 255, 255, 255, 22);

        // Dựng từ dưới lên: hàng "Đặt thiết kế · địa chỉ" → câu chào → tên studio → ảnh đại diện.
        $bottom = self::HEIGHT - $pad;
        $chipTop = $this->footerRow($img, $studio, $pad, $bottom, self::WIDTH - $pad, ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 30]);

        $head = $this->fit($this->headline($studio), 'medium', 30, 24, $maxW, 2);
        $headTop = $chipTop - 30 - $head['height'];
        $this->drawLines($img, $head, $pad, $headTop, $soft);

        $name = $this->fit($studio->name, 'bold', 76, 50, $maxW, 2);
        $nameTop = $headTop - 18 - $name['height'];
        $this->drawLines($img, $name, $pad, $nameTop, $white);

        $this->avatar($img, $studio, $pad, max($pad - 8, $nameTop - 28 - 92), 92, true);
    }

    // ------------------------------------------------------------------ Bố cục 2: chưa có ảnh bìa

    private function renderPaper(\GdImage $img, Studio $studio): void
    {
        $accent = $studio->accentHex();
        [$pr, $pg, $pb] = Color::rgb('#f4f4f2');
        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, imagecolorallocate($img, $pr, $pg, $pb));

        // Cột phải: tác phẩm tiêu biểu (hoặc khối màu nhấn).
        $panelX = 712;
        $this->renderWorks($img, $studio, $panelX, 0, self::WIDTH - $panelX, self::HEIGHT);

        // Thanh màu nhấn mảnh ở mép trái — chi tiết nhận diện, ăn theo màu studio chọn.
        [$ar, $ag, $ab] = Color::rgb($accent);
        imagefilledrectangle($img, 0, 0, 11, self::HEIGHT, imagecolorallocate($img, $ar, $ag, $ab));

        $pad = self::PAD;
        $maxW = $panelX - $pad - 56;
        [$ir, $ig, $ib] = Color::rgb('#1d1d22');
        [$sr, $sg, $sb] = Color::rgb('#3f3f46');
        $ink = imagecolorallocate($img, $ir, $ig, $ib);
        $ink2 = imagecolorallocate($img, $sr, $sg, $sb);

        $top = $pad;
        $this->avatar($img, $studio, $pad, $top, 92, false);
        $top += 92 + 34;

        $name = $this->fit($studio->name, 'bold', 58, 38, $maxW, 3);
        $this->drawLines($img, $name, $pad, $top, $ink);
        $top += $name['height'] + 20;

        $chipTop = $this->footerRow($img, $studio, $pad, self::HEIGHT - $pad, $panelX - 40, ['r' => 109, 'g' => 109, 'b' => 117, 'a' => 0]);
        $room = $chipTop - 30 - $top;
        $lines = max(1, min(3, (int) floor(($room + 8) / 40)));
        $head = $this->fit($this->headline($studio), 'medium', 25, 21, $maxW, $lines);
        if ($head['height'] <= $room) {
            $this->drawLines($img, $head, $pad, $top, $ink2);
        }
    }

    private function renderWorks(\GdImage $img, Studio $studio, int $x, int $y, int $w, int $h): void
    {
        $works = $studio->portfolioItems()->limit(3)->get()
            ->map(fn ($item) => $this->load($item->path))->filter()->values();

        if ($works->isEmpty()) {
            [$ar, $ag, $ab] = Color::rgb($studio->accentHex());
            imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, imagecolorallocate($img, $ar, $ag, $ab));
            if (function_exists('imagettftext')) {
                [$or, $og, $ob] = Color::rgb(Color::palette($studio->accentHex())['--on-accent'] ?? '#ffffff');
                $mono = $this->fit($studio->initials(), 'bold', 200, 120, $w - 80, 1);
                $tx = $x + (int) (($w - $mono['width']) / 2);
                $ty = $y + (int) (($h - $mono['height']) / 2);
                $this->drawLines($img, $mono, $tx, $ty, imagecolorallocatealpha($img, $or, $og, $ob, 10));
            }

            return;
        }

        $gap = 10;
        $cells = match ($works->count()) {
            1 => [[$x, $y, $w, $h]],
            2 => [[$x, $y, $w, (int) ($h * .6) - $gap / 2], [$x, $y + (int) ($h * .6) + $gap / 2, $w, $h - (int) ($h * .6) - $gap / 2]],
            default => [
                [$x, $y, $w, (int) ($h * .6) - $gap / 2],
                [$x, $y + (int) ($h * .6) + $gap / 2, (int) ($w / 2) - $gap / 2, $h - (int) ($h * .6) - $gap / 2],
                [$x + (int) ($w / 2) + $gap / 2, $y + (int) ($h * .6) + $gap / 2, $w - (int) ($w / 2) - $gap / 2, $h - (int) ($h * .6) - $gap / 2],
            ],
        };
        foreach ($works as $i => $work) {
            [$cx, $cy, $cw, $ch] = $cells[$i];
            $this->fillCover($img, $work, $cx, $cy, $cw, $ch);
            imagedestroy($work);
        }
    }

    // ------------------------------------------------------------------ Thành phần dùng chung

    /**
     * Hàng dưới cùng: nhãn màu nhấn "ĐẶT THIẾT KẾ" + địa chỉ trang (tên miền/dat/slug).
     *
     * @param  array{r:int,g:int,b:int,a:int}  $urlColor
     * @return int toạ độ y mép trên của hàng
     */
    private function footerRow(\GdImage $img, Studio $studio, int $x, int $bottom, int $right, array $urlColor): int
    {
        $accent = $studio->accentHex();
        [$ar, $ag, $ab] = Color::rgb($accent);
        [$or, $og, $ob] = Color::rgb(Color::palette($accent)['--on-accent'] ?? '#ffffff');

        $label = $studio->order_form_enabled ? 'ĐẶT THIẾT KẾ' : 'XEM TÁC PHẨM';
        $chip = $this->fit($label, 'bold', 19, 19, 600, 1);
        $chipH = 50;
        $chipW = $chip['width'] + 44;
        $top = $bottom - $chipH;
        $this->roundedRect($img, $x, $top, $chipW, $chipH, 8, imagecolorallocate($img, $ar, $ag, $ab));
        $this->drawLines($img, $chip, $x + 22, $top + (int) (($chipH - $chip['height']) / 2), imagecolorallocate($img, $or, $og, $ob));

        $url = $this->host().'/dat/'.$studio->slug;
        $u = $this->fit($url, 'medium', 22, 15, $right - $x - $chipW - 22, 1);
        $this->drawLines($img, $u, $x + $chipW + 22, $top + (int) (($chipH - $u['height']) / 2),
            imagecolorallocatealpha($img, $urlColor['r'], $urlColor['g'], $urlColor['b'], $urlColor['a']));

        return $top;
    }

    /** Ảnh đại diện tròn (hoặc chữ viết tắt trên nền màu nhấn), khử răng cưa bằng cách vẽ lớn rồi thu nhỏ. */
    private function avatar(\GdImage $img, Studio $studio, int $x, int $y, int $size, bool $ring): void
    {
        $scale = 3;
        $big = $size * $scale;
        $src = $studio->avatar_path ? $this->load($studio->avatar_path) : null;

        $tile = imagecreatetruecolor($big, $big);
        imagealphablending($tile, true);
        if ($src) {
            $this->fillCover($tile, $src, 0, 0, $big, $big);
            imagedestroy($src);
        } else {
            [$ar, $ag, $ab] = Color::rgb($studio->accentHex());
            imagefilledrectangle($tile, 0, 0, $big, $big, imagecolorallocate($tile, $ar, $ag, $ab));
            if (function_exists('imagettftext')) {
                [$or, $og, $ob] = Color::rgb(Color::palette($studio->accentHex())['--on-accent'] ?? '#ffffff');
                $t = $this->fit($studio->initials(), 'bold', (int) ($big * .3), (int) ($big * .2), (int) ($big * .8), 1);
                $this->drawLines($tile, $t, (int) (($big - $t['width']) / 2), (int) (($big - $t['height']) / 2), imagecolorallocate($tile, $or, $og, $ob));
            }
        }

        // Cắt tròn: pixel ngoài hình tròn → trong suốt.
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        $clear = imagecolorallocatealpha($tile, 0, 0, 0, 127);
        $r = $big / 2;
        $ringW = $ring ? 4 * $scale : 0;
        $white = imagecolorallocate($tile, 255, 255, 255);
        for ($py = 0; $py < $big; $py++) {
            for ($px = 0; $px < $big; $px++) {
                $d = sqrt(($px + .5 - $r) ** 2 + ($py + .5 - $r) ** 2);
                if ($d > $r) {
                    imagesetpixel($tile, $px, $py, $clear);
                } elseif ($ringW && $d > $r - $ringW) {
                    imagesetpixel($tile, $px, $py, $white);
                }
            }
        }

        $small = imagecreatetruecolor($size, $size);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagecopyresampled($small, $tile, 0, 0, 0, 0, $size, $size, $big, $big);
        imagedestroy($tile);

        imagealphablending($img, true);
        imagecopy($img, $small, $x, $y, 0, 0, $size, $size);
        imagedestroy($small);
    }

    /** Vẽ $src phủ kín khung (x, y, w, h), cắt phần thừa ở giữa — như object-fit: cover. */
    private function fillCover(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $scale);
        $ch = (int) round($h / $scale);
        imagecopyresampled($dst, $src, $x, $y, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, $cw, $ch);
    }

    private function roundedRect(\GdImage $img, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    private function load(string $path): ?\GdImage
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }
        $img = @imagecreatefromstring((string) $disk->get($path));

        return $img instanceof \GdImage ? $img : null;
    }

    // ------------------------------------------------------------------ Chữ

    private function font(string $weight): string
    {
        return resource_path('fonts/'.($weight === 'bold' ? 'BeVietnamPro-Bold.ttf' : 'BeVietnamPro-Medium.ttf'));
    }

    /**
     * Ngắt dòng theo bề rộng, thu nhỏ cỡ chữ (từ $max về $min) tới khi vừa $maxLines dòng; vẫn dài thì cắt và thêm "…".
     *
     * @return array{lines: list<string>, size: float, font: string, width: int, height: int, ascent: int, leading: int}
     */
    private function fit(string $text, string $weight, int $max, int $min, int $maxWidth, int $maxLines): array
    {
        $font = $this->font($weight);
        $text = trim(preg_replace('/\s+/u', ' ', $this->nfc($text)));

        for ($size = $max; $size >= $min; $size -= 2) {
            $lines = $this->wrap($text, $font, $size, $maxWidth);
            if (count($lines) <= $maxLines) {
                return $this->block($lines, $font, $size);
            }
        }

        $lines = array_slice($this->wrap($text, $font, $min, $maxWidth), 0, $maxLines);
        $last = rtrim(array_pop($lines), ' ,.;:–—-');
        while ($last !== '' && $this->width($last.'…', $font, $min) > $maxWidth) {
            $last = rtrim(mb_substr($last, 0, -1), ' ,.;:–—-');
        }
        $lines[] = $last.'…';

        return $this->block($lines, $font, $min);
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, float $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/ /u', $text) as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $this->width($try, $font, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
            // 1 từ dài hơn cả dòng (VD: tên viết liền) → cắt theo ký tự.
            while ($this->width($line, $font, $size) > $maxWidth && mb_strlen($line) > 1) {
                $cut = mb_strlen($line) - 1;
                while ($cut > 1 && $this->width(mb_substr($line, 0, $cut), $font, $size) > $maxWidth) {
                    $cut--;
                }
                $lines[] = mb_substr($line, 0, $cut);
                $line = mb_substr($line, $cut);
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    private function width(string $text, string $font, float $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box ? abs($box[2] - $box[0]) : 0;
    }

    /** @param list<string> $lines */
    private function block(array $lines, string $font, float $size): array
    {
        // Chiều cao chữ hoa có dấu (Ấ, Ỗ…) để dấu tiếng Việt không bị dòng trên đè lên.
        $box = imagettfbbox($size, 0, $font, 'ẤỖÁgjy');
        $ascent = (int) abs($box[7]);
        $descent = (int) abs($box[1]);
        $leading = (int) round(($ascent + $descent) * 0.96);
        $width = 0;
        foreach ($lines as $l) {
            $width = max($width, $this->width($l, $font, $size));
        }

        return [
            'lines' => $lines, 'size' => $size, 'font' => $font, 'width' => $width,
            'ascent' => $ascent, 'leading' => $leading,
            'height' => $leading * (count($lines) - 1) + $ascent + $descent,
        ];
    }

    private function drawLines(\GdImage $img, array $block, int $x, int $top, int $color): void
    {
        foreach ($block['lines'] as $i => $line) {
            imagettftext($img, $block['size'], 0, $x, $top + $block['ascent'] + $i * $block['leading'], $color, $block['font'], $line);
        }
    }

    private function nfc(string $text): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text) : $text;
    }
}
