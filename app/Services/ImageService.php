<?php

namespace App\Services;

use App\Models\Studio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ảnh trên Nét CHỈ để xem trước: thu nhỏ + nén WebP (~150–300 KB/ảnh) trước khi lưu.
 * File gốc chất lượng cao luôn ở Google Drive của freelancer — Nét không giữ file gốc.
 * Tên file ngẫu nhiên 32 ký tự để không đoán được đường dẫn ảnh của studio khác.
 */
class ImageService
{
    /**
     * @return array{path: string, width: int, height: int, bytes: int}
     */
    public function store(UploadedFile $file, Studio $studio, string $dir, int $maxPx, bool $square = false): array
    {
        $raw = @file_get_contents($file->getRealPath());
        $source = $raw !== false ? @imagecreatefromstring($raw) : false;

        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'Không đọc được ảnh "'.$file->getClientOriginalName().'". Hãy dùng JPG, PNG hoặc WebP.']);
        }

        $source = $this->applyExifOrientation($source, $file);
        [$w, $h] = [imagesx($source), imagesy($source)];

        if ($square) {
            $side = min($w, $h);
            $canvas = imagecreatetruecolor($maxPx, $maxPx);
            $this->prepareCanvas($canvas);
            imagecopyresampled($canvas, $source, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $maxPx, $maxPx, $side, $side);
            [$nw, $nh] = [$maxPx, $maxPx];
        } else {
            $scale = min(1, $maxPx / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $canvas = imagecreatetruecolor($nw, $nh);
            $this->prepareCanvas($canvas);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);
        }
        imagedestroy($source);

        [$contents, $ext] = $this->encode($canvas);
        imagedestroy($canvas);

        $bytes = strlen($contents);
        $this->assertQuota($studio, $bytes);

        $path = $studio->storageDir($dir).'/'.Str::random(32).'.'.$ext;
        Storage::disk('public')->put($path, $contents);
        $this->adjustUsage($studio, $bytes);

        return ['path' => $path, 'width' => $nw, 'height' => $nh, 'bytes' => $bytes];
    }

    /** Xoá 1 ảnh và trả lại dung lượng cho studio. */
    public function delete(Studio $studio, ?string $path, ?int $bytes = null): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk('public');
        $bytes ??= $disk->exists($path) ? (int) $disk->size($path) : 0;
        $disk->delete($path);
        $this->adjustUsage($studio, -$bytes);
    }

    public function assertQuota(Studio $studio, int $incoming): void
    {
        $studio->refresh();

        if ($studio->storage_bytes + $incoming > $studio->storageQuotaBytes()) {
            throw ValidationException::withMessages(['image' => 'Đã dùng hết dung lượng ảnh ('.config('net.storage_quota_mb').' MB). Hãy xoá bớt ảnh của các đơn cũ đã bàn giao.']);
        }
    }

    private function adjustUsage(Studio $studio, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        DB::table('studios')->where('id', $studio->id)
            ->update(['storage_bytes' => DB::raw('CASE WHEN storage_bytes + ('.(int) $delta.') < 0 THEN 0 ELSE storage_bytes + ('.(int) $delta.') END')]);
        $studio->storage_bytes = max(0, $studio->storage_bytes + $delta);
    }

    /** Nền trong suốt cho WebP/PNG có kênh alpha (logo nền trong vẫn giữ được). */
    private function prepareCanvas(\GdImage $canvas): void
    {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
    }

    /** @return array{0: string, 1: string} nội dung + đuôi file. Ưu tiên WebP, máy chủ không hỗ trợ thì dùng JPEG. */
    private function encode(\GdImage $img): array
    {
        $quality = (int) config('net.images.quality', 80);
        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($img, null, $quality);
            $ext = 'webp';
        } else {
            imagejpeg($img, null, $quality);
            $ext = 'jpg';
        }

        return [(string) ob_get_clean(), $ext];
    }

    /** Ảnh chụp điện thoại hay bị nằm ngang — xoay theo EXIF trước khi nén. */
    private function applyExifOrientation(\GdImage $img, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower((string) $file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return $img;
        }

        $exif = @exif_read_data($file->getRealPath());
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;

        if ($angle === 0) {
            return $img;
        }

        $rotated = imagerotate($img, $angle, 0);

        return $rotated ?: $img;
    }
}
