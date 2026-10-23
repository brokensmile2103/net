<?php

namespace App\Models;

use App\Support\Facebook;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'facebook', 'company', 'contact_url', 'note'];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Email có vẻ thật không (để Nét tự gửi email nhắc). @return array{ok: bool, reason: ?string, suggestion: ?string} */
    public function emailCheck(): array
    {
        return \App\Support\EmailCheck::check($this->email);
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name)) ?: [];
        $last = end($words) ?: $this->name;

        return mb_strtoupper(mb_substr((string) $last, 0, 2));
    }

    public function telLink(): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $this->phone);

        return $phone ? 'tel:'.$phone : null;
    }

    /** Link trang Facebook (null nếu khách chỉ ghi tên Facebook). */
    public function facebookUrl(): ?string
    {
        return Facebook::url($this->facebook);
    }

    public function facebookLabel(): string
    {
        return Facebook::label($this->facebook);
    }

    /** Mở thẳng khung chat Messenger với khách. */
    public function messengerUrl(): ?string
    {
        return Facebook::messengerUrl($this->facebook);
    }

    /** 1 dòng liên hệ ngắn cho danh sách: SĐT → email → Facebook. */
    public function contactLine(): string
    {
        return $this->phone ?: ($this->email ?: ($this->facebook ? 'Facebook: '.$this->facebookLabel() : 'Chưa có liên hệ'));
    }
}
