<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/** bước 2 của "Quên mật khẩu", xử lý link gửi qua email. */
class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return response()
            ->view('auth.reset-password', [
                'token' => $token,
                'email' => $request->query('email', ''),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $data,
            function ($user, $password) {
                // forceFill vì 'password' hợp lệ để mass-assign, nhưng dùng
                // forceFill cho đồng nhất với các chỗ khác từng đổi mật khẩu
                // trực tiếp (ProfileController@updatePassword) — rõ ràng đây
                // là hành động ghi mật khẩu có chủ đích, không lẫn với field khác.
                $user->forceFill(['password' => Hash::make($password), 'has_password' => true])->save();
            }
        );

        // Laravel không có sẵn bản dịch tiếng Việt cho các mã trạng thái này
        // (dự án không cài lang/vi) — tự map để không hiện tiếng Anh/mã thô
        // ra cho người dùng.
        if ($status !== Password::PASSWORD_RESET) {
            $messages = [
                Password::INVALID_USER => 'Không tìm thấy tài khoản phù hợp với email này.',
                Password::INVALID_TOKEN => 'Link đặt lại mật khẩu không hợp lệ hoặc đã được sử dụng. Vui lòng yêu cầu gửi lại link mới.',
                Password::RESET_THROTTLED => 'Bạn vừa yêu cầu đặt lại mật khẩu, vui lòng đợi ít phút rồi thử lại.',
            ];

            return back()->withErrors([
                'email' => $messages[$status] ?? 'Có lỗi xảy ra, vui lòng thử lại.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Mật khẩu đã được đặt lại. Vui lòng đăng nhập bằng mật khẩu mới.');
    }
}
