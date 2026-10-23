<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin có thể đóng đăng ký công khai (Cài đặt nền tảng) — dùng cho
 * bản tự host chỉ để một mình/nhóm nhỏ dùng. Khi đóng, các trang và bước
 * đăng ký đều bị chặn và đưa về trang đăng nhập.
 */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformSetting::registrationOpen()) {
            return redirect()->route('login')->withErrors(['phone' => 'Hệ thống hiện không nhận đăng ký mới.']);
        }

        return $next($request);
    }
}
