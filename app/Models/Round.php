<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 1 vòng gửi phương án (V1, V2...). draft → sent → approved | changes. */
class Round extends Model
{
    protected $fillable = ['number', 'note', 'status', 'sent_at', 'responded_at', 'response_note'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class)->orderBy('sort_order')->orderBy('id');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function label(): string
    {
        return 'V'.$this->number;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function awaitsClient(): bool
    {
        return $this->status === 'sent';
    }

    public function statusLabel(): string
    {
        return [
            'draft' => 'Bản nháp — khách chưa thấy',
            'sent' => 'Đã gửi, chờ khách xem',
            'approved' => 'Khách đã duyệt',
            'changes' => 'Khách yêu cầu sửa',
        ][$this->status] ?? $this->status;
    }
}
