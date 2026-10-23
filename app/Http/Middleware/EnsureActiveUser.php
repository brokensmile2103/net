<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tài khoản bị khoá (chủ nhóm khoá thành viên, Super Admin khoá người dùng) phải mất quyền
 * truy cập NGAY — không đợi tới lúc phiên đăng nhập hết hạn (phiên mặc định rất dài).
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['phone' => 'Tài khoản của bạn đã bị khoá.']);
        }

        return $next($request);
    }
}
