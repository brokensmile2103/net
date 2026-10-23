<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * 'role', 'is_active', 'google_id', 'has_password' CỐ TÌNH không nằm trong $fillable — các trường quyết định quyền hạn / danh tính đăng nhập,
     * không bao giờ được set qua mass-assignment (dùng forceCreate()/forceFill() với giá trị cố định).
     */
    protected $fillable = ['name', 'phone', 'email', 'password', 'avatar_url'];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'has_password' => 'boolean'];
    }

    /**
     * Có quyền chủ studio không. Super Admin cũng tính là chủ (có studio riêng, dùng được mọi
     * tính năng như freelancer) — chỉ khác là có thêm khu vực /sieu-quan-tri.
     */
    public function isOwner(): bool
    {
        return in_array($this->role, ['owner', 'platform_admin'], true);
    }

    /** Super Admin của cả hệ thống (vừa là chủ studio, vừa quản trị được mọi studio). */
    public function isPlatformAdmin(): bool
    {
        return $this->role === 'platform_admin';
    }

    /** Mỗi freelancer có đúng 1 studio. */
    public function studio()
    {
        return $this->hasOne(Studio::class, 'owner_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
