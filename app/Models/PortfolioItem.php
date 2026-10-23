<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** 1 ảnh "Tác phẩm tiêu biểu" trên trang đặt đơn của studio (ảnh xem trước đã nén, không phải file gốc). */
class PortfolioItem extends Model
{
    protected $fillable = ['caption', 'sort_order'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer', 'sort_order' => 'integer'];
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** "4 / 3" — giữ đúng tỉ lệ khung trước khi ảnh tải xong (không giật bố cục). */
    public function aspect(): string
    {
        return $this->width > 0 && $this->height > 0 ? $this->width.' / '.$this->height : '4 / 3';
    }
}
