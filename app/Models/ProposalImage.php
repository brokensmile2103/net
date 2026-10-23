<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProposalImage extends Model
{
    protected $fillable = ['path', 'width', 'height', 'bytes', 'sort_order'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer'];
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Tỉ lệ khung để giữ chỗ trước khi ảnh tải xong (tránh nhảy bố cục). */
    public function aspect(): string
    {
        return $this->width && $this->height ? $this->width.' / '.$this->height : '4 / 3';
    }
}
