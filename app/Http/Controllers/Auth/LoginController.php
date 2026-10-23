<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\StudioProvisioner;
use App\Services\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function show(): Response
    {
        // Trang này chứa token FormGuard phải LUÔN MỚI cho mỗi lượt tải — nếu
        // bị 1 lớp cache nào đó (Nginx cache, cache plugin WordPress dùng
        // chung VPS...) lưu lại, mọi người sẽ nhận cùng 1 token cũ, khiến
        // đăng nhập luôn báo lỗi "hết hạn" dù đúng mật khẩu. Header này ra
        // lệnh rõ ràng cho MỌI lớp cache không được lưu response này.
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request): RedirectResponse
    {
        // Chặn bot TRƯỚC khi kiểm tra tài khoản — cùng cơ chế với RegisterController.
        $isBot = ! FormGuard::verify(
            $request,
            'login',
            $request->input('form_ts'),
            $request->input('form_sig'),
            $request->input('hp_key'),
        );

        if ($isBot) {
            return back()->withErrors(['phone' => 'Phiên đăng nhập đã hết hạn hoặc có lỗi xảy ra. Vui lòng tải lại trang và thử lại.']);
        }

        $credentials = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login:'.normalize_phone($credentials['phone']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'phone' => "Bạn nhập sai quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ]);
        }

        // Thử đúng như gõ (tài khoản cũ có thể lưu kèm dấu cách), rồi thử dạng đã chuẩn hoá ("0912 345 678" → "0912345678").
        $normalized = normalize_phone($credentials['phone']);
        $ok = Auth::attempt($credentials, $request->boolean('remember'))
            || ($normalized !== $credentials['phone'] && Auth::attempt(['phone' => $normalized, 'password' => $credentials['password']], $request->boolean('remember')));

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'phone' => 'Số điện thoại hoặc mật khẩu không đúng.',
            ])->onlyInput('phone');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['phone' => 'Tài khoản của bạn đã bị khoá.']);
        }

        // Super Admin cũng là chủ studio: bản cài cũ chưa có studio riêng thì tạo ngay lúc này.
        app(StudioProvisioner::class)->ensureFor($user);

        // Studio bị Super Admin tạm khoá thì không vào được nữa dù mật khẩu vẫn đúng.
        if (! $user->studio || ! $user->studio->is_active) {
            Auth::logout();

            return back()->withErrors(['phone' => 'Studio của bạn đang tạm khoá hoặc chưa được kích hoạt. Vui lòng liên hệ hỗ trợ.']);
        }

        return redirect()->intended(route('today'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
