<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Models\User;
use App\Services\FormGuard;
use App\Services\StudioSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Đăng ký công khai: tạo cùng lúc tài khoản freelancer (role=owner) + studio của họ (kèm vài dịch vụ mẫu).
 * Giai đoạn đầu KHÔNG giới hạn tính năng theo gói cước.
 */
class RegisterController extends Controller
{
    public function show(): Response
    {
        // Trang chứa token FormGuard phải luôn mới — không để lớp cache nào lưu lại.
        return response()->view('auth.register')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request, StudioSetup $setup): RedirectResponse
    {
        if ($this->isBotOrThrottled($request)) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['studio_name' => $this->guardMessage($request)]);
        }

        $request->merge([
            'phone' => normalize_phone($request->input('phone')),
            'slug' => strtolower(trim((string) $request->input('slug'))),
        ]);

        $data = $request->validate([
            'studio_name' => 'required|string|max:100',
            'slug' => self::slugRules(),
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?\d{8,15}$/', 'unique:users,phone'],
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'accepted',
        ], self::slugMessages() + [
            'terms.accepted' => 'Bạn cần đồng ý với Điều khoản sử dụng và Chính sách bảo mật để tiếp tục.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'phone.unique' => 'Số điện thoại này đã có tài khoản — hãy đăng nhập.',
        ]);

        $user = DB::transaction(function () use ($data, $setup) {
            $user = User::forceCreate([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'is_active' => true,
            ]);

            $studio = new Studio(['name' => $data['studio_name'], 'slug' => $data['slug']]);
            $studio->owner_id = $user->id;
            $studio->is_active = true;
            $studio->save();

            $setup->seedServices($studio);

            return $user;
        });

        // Đăng nhập SAU KHI transaction đã commit — tránh session trỏ tới user đã bị rollback.
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('today')->with('status', 'Chào mừng đến với Nét! Gửi link đặt đơn cho khách, hoặc tự tạo đơn đầu tiên nhé.');
    }

    /** Bước "Hoàn tất" sau khi xác thực Google lần đầu: tên studio, địa chỉ trang đặt đơn, số điện thoại. */
    public function showGoogle(Request $request): Response|RedirectResponse
    {
        $pending = $this->googlePending($request);
        if (! $pending) {
            return redirect()->route('register')->withErrors(['studio_name' => 'Phiên đăng ký Google đã hết hạn, vui lòng thử lại.']);
        }

        return response()->view('auth.register-google', ['pending' => $pending])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function storeGoogle(Request $request, StudioSetup $setup): RedirectResponse
    {
        $pending = $this->googlePending($request);
        if (! $pending) {
            return redirect()->route('register')->withErrors(['studio_name' => 'Phiên đăng ký Google đã hết hạn, vui lòng thử lại.']);
        }

        if (! FormGuard::verify($request, 'register-google', $request->input('form_ts'), $request->input('form_sig'), $request->input('hp_key'))) {
            return back()->withInput()->withErrors(['studio_name' => 'Phiên đăng ký đã hết hạn. Vui lòng tải lại trang và thử lại.']);
        }

        $request->merge([
            'phone' => normalize_phone($request->input('phone')),
            'slug' => strtolower(trim((string) $request->input('slug'))),
        ]);

        $data = $request->validate([
            'studio_name' => 'required|string|max:100',
            'slug' => self::slugRules(),
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?\d{8,15}$/', 'unique:users,phone'],
            'terms' => 'accepted',
        ], self::slugMessages() + [
            'terms.accepted' => 'Bạn cần đồng ý với Điều khoản sử dụng và Chính sách bảo mật để tiếp tục.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'phone.unique' => 'Số điện thoại này đã có tài khoản — hãy đăng nhập bằng số điện thoại rồi liên kết Google trong Hồ sơ.',
        ]);

        if (User::where('google_id', $pending['google_id'])->exists()) {
            $request->session()->forget('google_pending');

            return redirect()->route('login')->withErrors(['google' => 'Tài khoản Google này đã được dùng để tạo studio. Hãy bấm “Tiếp tục với Google” để đăng nhập.']);
        }

        $email = $pending['email'] ?? null;
        if ($email && User::where('email', $email)->exists()) {
            $email = null;
        }

        $user = DB::transaction(function () use ($data, $pending, $email, $setup) {
            $user = User::forceCreate([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
                'has_password' => false,
                'google_id' => $pending['google_id'],
                'role' => 'owner',
                'is_active' => true,
            ]);

            $studio = new Studio(['name' => $data['studio_name'], 'slug' => $data['slug'], 'email' => $email]);
            $studio->owner_id = $user->id;
            $studio->is_active = true;
            $studio->save();

            $setup->seedServices($studio);

            return $user;
        });

        $request->session()->forget('google_pending');
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('today')->with('status', 'Chào mừng đến với Nét! Lần sau chỉ cần bấm “Tiếp tục với Google” để đăng nhập.');
    }

    /** Thông tin Google tạm giữ trong session — chỉ dùng trong 30 phút. */
    private function googlePending(Request $request): ?array
    {
        $pending = $request->session()->get('google_pending');

        if (! is_array($pending) || empty($pending['google_id']) || (time() - (int) ($pending['at'] ?? 0)) > 1800) {
            $request->session()->forget('google_pending');

            return null;
        }

        return $pending;
    }

    /** Luật cho địa chỉ trang đặt đơn — dùng chung với Cài đặt. */
    public static function slugRules(?int $ignoreStudioId = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(config('net.reserved_slugs')),
            Rule::unique('studios', 'slug')->ignore($ignoreStudioId),
        ];
    }

    public static function slugMessages(): array
    {
        return [
            'slug.regex' => 'Địa chỉ chỉ gồm chữ thường không dấu, số và dấu gạch ngang (VD: may-studio).',
            'slug.not_in' => 'Địa chỉ này dành riêng cho hệ thống, hãy chọn tên khác.',
            'slug.unique' => 'Địa chỉ này đã có người dùng, hãy thử tên khác.',
            'slug.min' => 'Địa chỉ cần ít nhất 3 ký tự.',
        ];
    }

    /** Honeypot + token thời gian ký HMAC, rồi giới hạn tần suất theo IP. true = chặn. */
    private function isBotOrThrottled(Request $request): bool
    {
        if (! FormGuard::verify($request, 'register', $request->input('form_ts'), $request->input('form_sig'), $request->input('hp_key'))) {
            return true;
        }

        $key = 'register:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return true;
        }
        RateLimiter::hit($key, 3600);

        return false;
    }

    private function guardMessage(Request $request): string
    {
        $key = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return 'Bạn thao tác quá nhiều lần. Vui lòng thử lại sau khoảng '.(int) ceil(RateLimiter::availableIn($key) / 60).' phút.';
        }

        return 'Phiên đăng ký đã hết hạn hoặc có lỗi xảy ra. Vui lòng tải lại trang và thử lại.';
    }
}
