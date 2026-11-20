<?php

namespace App\Support;

/**
 * Đo + ngắt dòng chữ bằng GD/FreeType (không phụ thuộc Laravel) cho các ảnh Nét tự vẽ.
 *
 * Mọi số đo tính bằng "pixel logic" của ảnh thành phẩm; đo ở độ phân giải gấp MEASURE_SCALE lần rồi chia lại
 * để số đo không phụ thuộc vào độ phân giải thật khi vẽ (GdCanvas có thể vẽ ở 1×, 1.5× hay 2× rồi thu nhỏ).
 * Nhờ vậy ngắt dòng quyết định MỘT lần lúc dựng bố cục, lúc vẽ chỉ việc đặt chữ vào đúng chỗ.
 */
final class GdText
{
    private const MEASURE_SCALE = 2;

    /** @var array<string, array{asc: float, desc: float, cap: float}> */
    private array $metrics = [];

    /** @var array<string, float> */
    private array $advances = [];

    /**
     * @param  array<string, string>  $fonts  khoá → đường dẫn file .ttf (đã chọn phương án dự phòng, file phải tồn tại)
     */
    public function __construct(private readonly array $fonts) {}

    public function path(string $font): string
    {
        return $this->fonts[$font] ?? $this->fonts['body'];
    }

    /** Chiều rộng 1 chuỗi (1 dòng). */
    public function width(string $text, string $font, float $size): float
    {
        if ($text === '') {
            return 0.0;
        }
        $box = imagettfbbox($size * self::MEASURE_SCALE, 0, $this->path($font), $text);

        return $box ? abs($box[2] - $box[0]) / self::MEASURE_SCALE : 0.0;
    }

    /** Độ rộng "bước chữ" của 1 ký tự (kể cả dấu cách) — dùng khi giãn chữ. */
    public function advance(string $char, string $font, float $size): float
    {
        $key = $font.'|'.$size.'|'.$char;

        return $this->advances[$key] ??= max(0.0, $this->width('x'.$char.'x', $font, $size) - $this->width('xx', $font, $size));
    }

    /**
     * Chiều cao phần trên/dưới đường cơ sở. asc tính theo chữ hoa có dấu chồng (Ấ, Ỗ…) để dấu tiếng Việt
     * không bị dòng trên đè lên; cap là chiều cao chữ hoa thường (H).
     *
     * @return array{asc: float, desc: float, cap: float}
     */
    public function metrics(string $font, float $size): array
    {
        $key = $font.'|'.$size;
        if (! isset($this->metrics[$key])) {
            $path = $this->path($font);
            $m = self::MEASURE_SCALE;
            $a = imagettfbbox($size * $m, 0, $path, 'ẤỖÁ');
            $d = imagettfbbox($size * $m, 0, $path, 'gjyợ');
            $c = imagettfbbox($size * $m, 0, $path, 'H');
            $this->metrics[$key] = [
                'asc' => $a ? abs($a[7]) / $m : $size,
                'desc' => $d ? abs($d[1]) / $m : $size * .25,
                'cap' => $c ? abs($c[7]) / $m : $size * .7,
            ];
        }

        return $this->metrics[$key];
    }

    /** Gọn khoảng trắng, đưa về dạng NFC, xuống dòng → " · " (mô tả dịch vụ hay ghi mỗi ý một dòng). */
    public function clean(string $text, bool $newlinesAsDots = true): string
    {
        $text = strip_tags($text);
        if ($newlinesAsDots) {
            $text = preg_replace('/\s*[\r\n]+\s*/u', ' · ', trim($text)) ?? $text;
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text) : $text;
    }

    /**
     * Ngắt dòng theo bề rộng, thu nhỏ cỡ chữ (từ $size về $minSize) tới khi vừa $maxLines dòng; vẫn dài thì cắt + "…".
     *
     * @return array{lines: list<string>, font: string, size: float, w: float, h: float, asc: float, desc: float, cap: float, lead: float}
     */
    public function block(string $text, string $font, float $size, float $maxW, int $maxLines = 1, ?float $minSize = null, float $lineHeight = 1.0): array
    {
        $text = $this->clean($text);
        $minSize = min($minSize ?? $size, $size);
        $maxLines = max(1, $maxLines);

        for ($s = $size; $s >= $minSize - 0.01; $s -= 2) {
            $lines = $this->wrap($text, $font, $s, $maxW);
            if (count($lines) <= $maxLines) {
                return $this->make($lines, $font, $s, $lineHeight);
            }
        }

        $lines = array_slice($this->wrap($text, $font, $minSize, $maxW), 0, $maxLines);
        $last = rtrim((string) array_pop($lines), ' ,.;:–—-·');
        while ($last !== '' && $this->width($last.'…', $font, $minSize) > $maxW) {
            $last = rtrim(mb_substr($last, 0, -1), ' ,.;:–—-·');
        }
        $lines[] = $last.'…';

        return $this->make($lines, $font, $minSize, $lineHeight);
    }

    /** @return list<string> */
    public function wrap(string $text, string $font, float $size, float $maxW): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/ /u', $text) ?: [] as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $this->width($try, $font, $size) > $maxW) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
            // 1 từ dài hơn cả dòng (VD: tên viết liền) → cắt theo ký tự.
            while (mb_strlen($line) > 1 && $this->width($line, $font, $size) > $maxW) {
                $cut = mb_strlen($line) - 1;
                while ($cut > 1 && $this->width(mb_substr($line, 0, $cut), $font, $size) > $maxW) {
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

    /** Khối chữ 1 dòng, không ngắt/cắt (nhãn ngắn, giá tiền) — rộng bao nhiêu thì rộng. */
    public function line(string $text, string $font, float $size, float $lineHeight = 1.0): array
    {
        return $this->make([$this->clean($text)], $font, $size, $lineHeight);
    }

    /** Chiều rộng chuỗi khi giãn chữ $tracking px giữa các ký tự. */
    public function spacedWidth(string $text, string $font, float $size, float $tracking): float
    {
        $w = 0.0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $w += $this->advance($ch, $font, $size) + $tracking;
        }

        return max(0.0, $w - $tracking);
    }

    /** @param list<string> $lines */
    private function make(array $lines, string $font, float $size, float $lineHeight): array
    {
        $m = $this->metrics($font, $size);
        $lead = ($m['asc'] + $m['desc']) * $lineHeight;
        $w = 0.0;
        foreach ($lines as $l) {
            $w = max($w, $this->width($l, $font, $size));
        }

        return [
            'lines' => $lines, 'font' => $font, 'size' => $size, 'w' => $w,
            'asc' => $m['asc'], 'desc' => $m['desc'], 'cap' => $m['cap'], 'lead' => $lead,
            'h' => $lead * (count($lines) - 1) + $m['asc'] + $m['desc'],
        ];
    }
}
