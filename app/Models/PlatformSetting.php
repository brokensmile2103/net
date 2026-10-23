<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cài đặt cấp nền tảng (key/value) — Super Admin chỉnh tại /sieu-quan-tri/cai-dat.
 */
class PlatformSetting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            return static::query()->whereKey($key)->value('value') ?? $default;
        } catch (\Throwable) {
            // Chưa chạy migration (bản cũ vừa cập nhật code) → dùng mặc định thay vì lỗi 500.
            return $default;
        }
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrInsert(['key' => $key], ['value' => $value]);
    }

    /** Mặc định MỞ đăng ký — chỉ đóng khi Super Admin chủ động tắt. */
    public static function registrationOpen(): bool
    {
        return static::get('registration_open', '1') !== '0';
    }
}
