<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\StudioProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Đăng nhập / đăng ký bằng Google — 1 nút dùng chung cho cả 2 việc.
 *
 * 1. google_id đã liên kết với 1 tài khoản → đăng nhập.
 * 2. Đang đăng nhập và bấm "Liên kết Google" ở Hồ sơ → gắn google_id vào chính tài khoản đó.
 * 3. Hoàn toàn mới → giữ tạm thông tin Google trong session, sang bước "Hoàn tất" để nhập tên studio,
 *    địa chỉ trang đặt đơn và số điện thoại (bảng users bắt buộc có SĐT) — xem RegisterController::storeGoogle().
 *
 * KHÔNG tự liên kết theo email: email trong Nét là "email khôi phục" người dùng tự gõ, chưa được xác minh,
 * nên ghép theo email có thể đưa người này vào tài khoản của người khác. Trùng email → hướng dẫn đăng nhập
 * bằng SĐT rồi tự liên kết ở Hồ sơ.
 */
class GoogleAuthController extends Controller
{
    private const BROKEN = 'Đăng nhập Google đang gặp sự cố. Vui lòng thử lại sau ít phút, hoặc đăng nhập bằng số điện thoại.';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! self::enabled()) {
            return redirect()->route(Auth::check() ? 'profile.edit' : 'login')->withErrors(['google' => 'Đăng nhập Google hiện chưa được bật.']);
        }

        if (Auth::check()) {
            if (! $request->boolean('lien-ket')) {
                return redirect()->route('home');
            }
            $request->session()->put('google_link', Auth::id());
        } else {
            $request->session()->forget('google_link');
        }

        try {
            return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
        } catch (\Throwable $e) {
            // VD: thiếu thư viện / cấu hình sai. Ghi đủ chi tiết vào storage/logs/laravel.log thay vì trang lỗi 500 trắng.
            report($e);
            $request->session()->forget('google_link');

            return redirect()->route(Auth::check() ? 'profile.edit' : 'login')->withErrors(['google' => self::BROKEN]);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $linkingFor = $request->session()->pull('google_link');
        $linking = $linkingFor && Auth::check() && (int) $linkingFor === (int) Auth::id();
        $fail = fn (string $msg) => redirect()->route($linking ? 'profile.edit' : 'login')->withErrors(['google' => $msg]);

        if (! self::enabled()) {
            return $fail('Đăng nhập Google hiện chưa được bật.');
        }

        // Người dùng bấm "Huỷ" ở màn hình Google → Google trả về ?error=access_denied.
        if ($request->filled('error')) {
            return $fail('Bạn đã huỷ đăng nhập Google.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // Phiên hết hạn / mở callback ở tab khác / cookie phiên không được gửi kèm (kiểm tra SESSION_DOMAIN, SESSION_SECURE_COOKIE).
            Log::warning('Đăng nhập Google: state không khớp', ['ip' => $request->ip()]);

            return $fail('Phiên đăng nhập Google đã hết hạn, vui lòng bấm “Tiếp tục với Google” lại.');
        } catch (\Throwable $e) {
            // Sai Client Secret, redirect_uri không khớp, server không gọi ra được Google…
            report($e);

            return $fail('Đăng nhập Google không thành công, vui lòng thử lại.');
        }

        try {
            return $this->finish($request, $google, $linking, $fail);
        } catch (\Throwable $e) {
            // Lỗi phía Nét (VD: database thiếu cột) — ghi log đầy đủ, không để người dùng thấy trang 500.
            report($e);

            return $fail(self::BROKEN);
        }
    }

    /** Phần xử lý sau khi Google đã xác thực xong: liên kết / đăng nhập / chuyển sang bước hoàn tất đăng ký. */
    private function finish(Request $request, SocialiteUser $google, bool $linking, \Closure $fail): RedirectResponse
    {
        $googleId = (string) $google->getId();
        if ($googleId === '') {
            return $fail('Google không trả về thông tin tài khoản, vui lòng thử lại.');
        }
        $owner = User::where('google_id', $googleId)->first();

        // Liên kết từ trang Hồ sơ.
        if ($linking) {
            if ($owner && $owner->id !== Auth::id()) {
                return $fail('Tài khoản Google này đã liên kết với một tài khoản Nét khác.');
            }
            Auth::user()->forceFill(['google_id' => $googleId])->save();

            return redirect()->route('profile.edit')->with('status', 'Đã liên kết Google'.($google->getEmail() ? ' ('.$google->getEmail().')' : '').'. Lần sau bấm “Tiếp tục với Google” để đăng nhập.');
        }

        if (Auth::check()) {
            return redirect()->route('home');
        }

        if ($owner) {
            if (! $owner->is_active) {
                return $fail('Tài khoản của bạn đã bị khoá.');
            }
            // Super Admin cũng là chủ studio: bản cài cũ chưa có studio riêng thì tạo ngay lúc này.
            app(StudioProvisioner::class)->ensureFor($owner);
            if ($owner->isOwner() && (! $owner->studio || ! $owner->studio->is_active)) {
                return $fail('Studio của bạn đang tạm khoá hoặc chưa được kích hoạt. Vui lòng liên hệ hỗ trợ.');
            }

            Auth::login($owner, remember: true);
            $request->session()->regenerate();

            return redirect()->intended(route('today'));
        }

        $email = $google->getEmail();
        if ($email && User::where('email', $email)->exists()) {
            return $fail('Email '.$email.' đã gắn với một tài khoản Nét. Hãy đăng nhập bằng số điện thoại, rồi bấm “Liên kết Google” trong Hồ sơ cá nhân.');
        }

        // Đăng ký đang đóng: chỉ tài khoản ĐÃ CÓ trong hệ thống mới được đăng nhập bằng Google.
        if (! PlatformSetting::registrationOpen()) {
            return $fail('Tài khoản Google này chưa có trong hệ thống và hệ thống hiện không nhận đăng ký mới.');
        }

        $request->session()->put('google_pending', [
            'google_id' => $googleId,
            'email' => $email,
            'name' => $google->getName() ?: $google->getNickname() ?: '',
            'avatar' => $google->getAvatar(),
            'at' => time(),
        ]);

        return redirect()->route('register.google');
    }

    /** Gỡ liên kết — chỉ khi đã có mật khẩu, kẻo tự khoá mình ngoài cửa. */
    public function unlink(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->has_password) {
            return back()->withErrors(['google' => 'Hãy đặt mật khẩu trước khi gỡ liên kết Google, nếu không bạn sẽ không đăng nhập được.']);
        }

        $user->forceFill(['google_id' => null])->save();

        return back()->with('status', 'Đã gỡ liên kết Google.');
    }
}
