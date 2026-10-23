<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 1 email nhắc Nét đã tự gửi cho khách (review | quote | payment). */
class OrderReminder extends Model
{
    public $timestamps = false;

    protected $fillable = ['kind', 'email', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        return config('net.reminders.kinds.'.$this->kind.'.label', $this->kind);
    }
}
