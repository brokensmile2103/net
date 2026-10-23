<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 1 dòng trên dòng thời gian của đơn (visible = khách thấy trên trang theo dõi). */
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'actor', 'visible', 'message'];

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'created_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function icon(): string
    {
        return [
            'created' => 'fa-inbox', 'status' => 'fa-arrow-right', 'quote' => 'fa-file-signature', 'quote_accepted' => 'fa-handshake',
            'round_sent' => 'fa-paper-plane', 'approved' => 'fa-circle-check', 'changes' => 'fa-rotate', 'chosen' => 'fa-hand-pointer',
            'feedback' => 'fa-comment', 'payment' => 'fa-money-bill-wave', 'delivered' => 'fa-box-archive', 'cancelled' => 'fa-ban',
            'note' => 'fa-note-sticky', 'link' => 'fa-link',
        ][$this->type] ?? 'fa-circle';
    }
}
