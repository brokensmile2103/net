<?php

namespace App\Services\PriceList;

use App\Support\GdCanvas;
use App\Support\GdText;

/**
 * Một kiểu vẽ ảnh bảng giá — tương ứng 1 mẫu giao diện trang khách (config('net.page_themes')).
 *
 * build() đo chữ + tính bố cục (chưa cần tờ vẽ) rồi trả về chiều cao ảnh và hàm vẽ; PriceListImage tạo tờ vẽ
 * đúng chiều cao đó và gọi hàm vẽ. Mọi toạ độ là pixel logic, ảnh thành phẩm rộng W px.
 *
 * Dữ liệu $d xem PriceListImage::payload().
 */
abstract class Theme
{
    public const W = 1200;

    /** @return array{height: float, paint: \Closure(GdCanvas): void} */
    abstract public function build(GdText $t, array $d): array;

    /**
     * Đo từng dòng dịch vụ: tên, mô tả, dòng phụ (thời gian · lần sửa · cọc) và giá.
     * $o: nameFont, nameSize, nameMin, nameLines, priceFont, priceSize, priceMin, descFont, descSize, descLines,
     * metaFont, metaSize, gap (khoảng giữa cột chữ và cột giá), priceMaxShare (giá chiếm tối đa bao nhiêu phần bề rộng).
     *
     * @return list<array{name: array, desc: ?array, meta: ?array, price: array, known: bool, n: int}>
     */
    protected function rows(GdText $t, array $d, float $width, array $o): array
    {
        $priceMax = $width * ($o['priceMaxShare'] ?? 0.42);
        $prices = [];
        $pw = 0.0;
        foreach ($d['services'] as $s) {
            $font = $s['known'] ? $o['priceFont'] : ($o['priceFontUnknown'] ?? $o['priceFont']);
            $size = $s['known'] ? $o['priceSize'] : ($o['priceSizeUnknown'] ?? $o['priceSize'] * 0.72);
            $prices[] = $t->block($s['price'], $font, $size, $priceMax, 1, ($o['priceMin'] ?? $size * 0.6));
            $pw = max($pw, end($prices)['w'] + ($s['known'] ? ($o['priceExtra'] ?? 0) : 0));
        }
        $textW = $width - $pw - ($o['gap'] ?? 28);

        $rows = [];
        foreach ($d['services'] as $i => $s) {
            $rows[] = [
                'name' => $t->block($s['name'], $o['nameFont'], $o['nameSize'], $textW, $o['nameLines'] ?? 2, $o['nameMin'] ?? $o['nameSize'] * 0.7, $o['nameLead'] ?? 0.98),
                'desc' => $s['desc'] !== '' ? $t->block($s['desc'], $o['descFont'], $o['descSize'], $o['descWidth'] ?? $textW, $o['descLines'] ?? 3, null, $o['descLead'] ?? 1.02) : null,
                'meta' => $s['meta'] !== '' ? $t->block($s['meta'], $o['metaFont'], $o['metaSize'], $o['metaWidth'] ?? $textW, 1, $o['metaSize'] * 0.8) : null,
                'price' => $prices[$i],
                'known' => $s['known'],
                'n' => $i + 1,
            ];
        }

        return $rows;
    }

    /** Chiều cao cột chữ của 1 dòng: tên + mô tả + dòng phụ. */
    protected function stackHeight(array $row, float $gapDesc, float $gapMeta): float
    {
        return $row['name']['h']
            + ($row['desc'] ? $gapDesc + $row['desc']['h'] : 0)
            + ($row['meta'] ? $gapMeta + $row['meta']['h'] : 0);
    }

    /** Vẽ cột chữ (tên → mô tả → dòng phụ); trả về mép dưới. */
    protected function paintStack(GdCanvas $c, array $row, float $x, float $y, array $col, float $gapDesc, float $gapMeta): float
    {
        $c->text($row['name'], $x, $y, $col['name']);
        $y += $row['name']['h'];
        if ($row['desc']) {
            $y += $gapDesc;
            $c->text($row['desc'], $x, $y, $col['desc'], 'l', $col['descOpacity'] ?? 1.0);
            $y += $row['desc']['h'];
        }
        if ($row['meta']) {
            $y += $gapMeta;
            $c->text($row['meta'], $x, $y, $col['meta'], 'l', $col['metaOpacity'] ?? 1.0);
            $y += $row['meta']['h'];
        }

        return $y;
    }

    /** Toạ độ y mép trên của giá sao cho đường cơ sở trùng đường cơ sở dòng tên đầu tiên (dòng đầu tại $y). */
    protected function priceTop(array $row, float $y): float
    {
        return $y + $row['name']['asc'] - $row['price']['asc'];
    }

    /** Chữ lớn có thể phải xuống dòng: cỡ chữ lớn nhất vừa $lines dòng. */
    protected function title(GdText $t, string $text, string $font, float $size, float $min, float $width, int $lines, float $lead = 0.95): array
    {
        return $t->block($text, $font, $size, $width, $lines, $min, $lead);
    }

    /**
     * Mép trên của chữ 1 dòng sao cho chiều cao chữ hoa nằm giữa đường ngang $cy (căn giữa nhãn trong nút).
     */
    protected function centerTop(GdText $t, string $font, float $size, float $cy): float
    {
        $m = $t->metrics($font, $size);

        return $cy + $m['cap'] / 2 - $m['asc'];
    }

    /** "Cập nhật 10/2026" → dùng chung. */
    protected function updated(array $d): string
    {
        return $d['updated'];
    }

    /** Dòng liên hệ gộp: "0901 234 567 · mail@abc.vn". */
    protected function contactLine(array $d): string
    {
        return implode('  ·  ', $d['contact']);
    }

    /** Nhãn nút kêu gọi (null nếu studio đang tắt nhận đơn). */
    protected function cta(array $d): ?string
    {
        return $d['url'] ? 'ĐẶT THIẾT KẾ' : null;
    }
}
