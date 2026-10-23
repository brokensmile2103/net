<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Quên mật khẩu". Đăng nhập của Nét dùng SỐ ĐIỆN
 * THOẠI (không phải email), nên form này hỏi SĐT — email chỉ dùng để GỬI link
 * đặt lại (Profile > Email). Tài khoản CHƯA điền email sẽ không nhận được gì.
 *
 * Nguyên tắc quan trọng nhất xuyên suốt file này: LUÔN trả về đúng 1 thông
 * báo chung chung, GIỐNG HỆT NHAU dù số điện thoại có tồn tại hay không, có
 * email hay không — không bao giờ để lộ qua thông báo khác nhau việc 1 số
 * điện thoại có đăng ký trong hệ thống hay không (chống dò/enumerate số điện
 * thoại của người khác).
 */
class ForgotPasswordController extends Controller
{
    public function show(): Response
    {
        return response()
            ->view('auth.forgot-password')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function send(Request $request): RedirectResponse
    {
        // Chặn bot — cùng cơ chế Honeypot + token thời gian với Login/Register.
        $isBot = ! FormGuard::verify(
            $request,
            'forgot-password',
            $request->input('form_ts'),
            $request->input('form_sig'),
            $request->input('hp_key'),
        );

        if ($isBot) {
            return back()->withErrors(['phone' => 'Phiên yêu cầu đã hết hạn hoặc có lỗi xảy ra. Vui lòng tải lại trang và thử lại.']);
        }

        $request->validate(['phone' => 'required|string|max:20']);

        // Chống gửi hàng loạt — giới hạn theo IP (KHÔNG theo số điện thoại
        // nhập vào, vì key theo input do người dùng tự gõ sẽ dễ bị né bằng
        // cách đổi số liên tục). Laravel's Password broker bên dưới còn tự
        // chặn thêm 60 giây/email (xem config/auth.php: passwords.users.throttle)
        // để không spam email tới CÙNG 1 người nhiều lần liên tiếp.
        $throttleKey = 'forgot-password:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors([
                'phone' => "Bạn thao tác quá nhiều lần. Vui lòng thử lại sau khoảng {$minutes} phút.",
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $user = User::where('phone', $request->input('phone'))->first()
            ?? User::where('phone', normalize_phone($request->input('phone')))->first();

        // Chỉ thực sự gửi khi: (1) số điện thoại tồn tại, VÀ (2) tài khoản đó
        // đã điền email ở Profile — đúng yêu cầu "ai điền email mới quên được
        // mật khẩu". Dùng broker chuẩn của Laravel (Password::sendResetLink),
        // tận dụng cơ chế token ký/hết hạn/chống tái sử dụng đã có sẵn, không
        // tự chế lại để tránh sai sót bảo mật.
        if ($user && $user->email) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'Nếu số điện thoại này đã đăng ký email khôi phục, chúng tôi đã gửi link đặt lại mật khẩu tới email đó. Vui lòng kiểm tra hộp thư (kể cả mục Spam/Quảng cáo).');
    }
}
