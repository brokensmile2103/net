<?php

namespace App\Services;

use App\Models\Studio;
use App\Support\Color;
use Illuminate\Support\Facades\Storage;

/**
 * Favicon của trang khách (đặt đơn, theo dõi, lịch hẹn) = ảnh đại diện của studio. Chưa có ảnh thì vẽ chữ viết tắt
 * trên màu nhấn. Ảnh PNG tạo 1 lần rồi lưu ở storage/app/public/studios/{id}/icon/, tên file theo dấu vân tay nên
 * đổi ảnh / màu / tên là trình duyệt lấy icon mới.
 *  - 32, 64, 192, 512: hình tròn, nền trong suốt (tab trình duyệt, Android).
 *  - 180: hình vuông kín nền (apple-touch-icon — iOS tự bo góc).
 */
class StudioIcon
{
    public const SIZES = [32, 64, 180, 192, 512];

    private const VERSION = 1;

    public function fingerprint(Studio $studio): string
    {
        return substr(sha1(json_encode([self::VERSION, $studio->avatar_path, $studio->accentHex(), $studio->initials()])), 0, 10);
    }

    /** Đường dẫn trên disk "public" (tạo nếu chưa có), null nếu máy chủ không có GD. */
    public function path(Studio $studio, int $size): ?string
    {
        if (! in_array($size, self::SIZES, true) || ! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $disk = Storage::disk('public');
        $dir = $studio->storageDir('icon');
        $path = $dir.'/'.$this->fingerprint($studio).'-'.$size.'.png';
        if ($disk->exists($path)) {
            return $path;
        }

        $png = $this->render($studio, $size);
        if ($png === null) {
            return null;
        }
        // Xoá icon cũ (dấu vân tay khác) — không tính vào hạn mức ảnh, chỉ vài KB.
        foreach ($disk->files($dir) as $old) {
            if (! str_starts_with(basename($old), $this->fingerprint($studio).'-')) {
                $disk->delete($old);
            }
        }
        $disk->put($path, $png);

        return $path;
    }

    public function render(Studio $studio, int $size): ?string
    {
        $round = $size !== 180;
        $ss = $round ? ($size >= 192 ? 2 : 4) : 1; // vẽ to hơn rồi thu nhỏ để viền tròn mượt
        $d = $size * $ss;
        $img = imagecreatetruecolor($d, $d);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $src = null;
        if ($studio->avatar_path && Storage::disk('public')->exists($studio->avatar_path)) {
            $src = @imagecreatefromstring((string) Storage::disk('public')->get($studio->avatar_path)) ?: null;
        }
        if ($src) {
            // Cắt giữa thành hình vuông.
            $w = imagesx($src);
            $h = imagesy($src);
            $side = min($w, $h);
            imagecopyresampled($img, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $d, $d, $side, $side);
            imagedestroy($src);
        } else {
            $accent = $studio->accentHex();
            [$r, $g, $b] = Color::rgb($accent);
            imagefilledrectangle($img, 0, 0, $d, $d, imagecolorallocate($img, $r, $g, $b));
            $fg = Color::contrast($accent, '#ffffff') >= 3 ? '#ffffff' : '#1d1d22';
            [$fr, $fgc, $fb] = Color::rgb($fg);
            $font = resource_path('fonts/BeVietnamPro-Bold.ttf');
            $text = mb_substr($studio->initials(), 0, 2);
            if (is_file($font) && function_exists('imagettftext')) {
                $fs = $d * ($size <= 32 ? 0.46 : 0.38);
                do {
                    $box = imagettfbbox($fs, 0, $font, $text);
                    $tw = $box[2] - $box[0];
                    $fs *= 0.92;
                } while ($tw > $d * 0.78 && $fs > 4);
                $fs /= 0.92;
                $th = $box[1] - $box[7];
                imagettftext($img, $fs, 0, (int) (($d - $tw) / 2 - $box[0]), (int) (($d + $th) / 2 - $box[1]), imagecolorallocate($img, $fr, $fgc, $fb), $font, $text);
            }
        }

        if ($round) {
            imagealphablending($img, false);
            $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
            $rad = $d / 2;
            for ($y = 0; $y < $d; $y++) {
                for ($x = 0; $x < $d; $x++) {
                    if ((($x + .5 - $rad) ** 2 + ($y + .5 - $rad) ** 2) > $rad * $rad) {
                        imagesetpixel($img, $x, $y, $clear);
                    }
                }
            }
            $out = imagecreatetruecolor($size, $size);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $d, $d);
            imagedestroy($img);
            $img = $out;
        }

        ob_start();
        imagepng($img, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png !== '' ? $png : null;
    }
}
