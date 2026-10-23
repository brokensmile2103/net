<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Link Google Drive (hoặc Figma, Dropbox...) gắn với đơn: tài liệu khách gửi, file bàn giao... */
class OrderLink extends Model
{
    protected $fillable = ['kind', 'label', 'url', 'from_client'];

    protected function casts(): array
    {
        return ['from_client' => 'boolean'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function meta(): array
    {
        return config('net.link_kinds.'.$this->kind, config('net.link_kinds.other'));
    }

    /** Tên hiển thị: nhãn người dùng đặt, hoặc tên dịch vụ đoán từ domain. */
    public function displayLabel(): string
    {
        return $this->label ?: link_host_label($this->url);
    }
}
