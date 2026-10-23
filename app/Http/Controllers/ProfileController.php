<?php

namespace App\Http\Controllers;

use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Hồ sơ cá nhân (tên, email khôi phục, mật khẩu, xoá tài khoản). Luôn dùng auth()->user() — không nhận id qua URL.
 * Ảnh đại diện nằm ở hồ sơ studio (Cài đặt), không ở đây.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255|unique:users,email,'.$user->id,
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email'] ?? null]);

        return back()->with('status', 'Đã cập nhật hồ sơ.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Tài khoản tạo bằng Google chưa có mật khẩu → "Đặt mật khẩu" không cần mật khẩu cũ.
        $data = $request->validate([
            'current_password' => $user->has_password ? 'required|string' : 'nullable',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($user->has_password && ! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $first = ! $user->has_password;
        $user->forceFill(['password' => Hash::make($data['password']), 'has_password' => true])->save();

        return back()->with('status', $first ? 'Đã đặt mật khẩu — giờ bạn đăng nhập được bằng số điện thoại hoặc Google.' : 'Đã đổi mật khẩu.');
    }

    /**
     * Freelancer tự xoá tài khoản (xem AccountDeletionService). Chỉ role=owner — Super Admin không tự xoá được.
     * Bắt buộc xác nhận 2 lớp vì KHÔNG THỂ HOÀN TÁC: nhập lại mật khẩu + gõ đúng cụm xác nhận.
     */
    public function destroy(Request $request, AccountDeletionService $deletionService): RedirectResponse
    {
        $user = $request->user();

        // Super Admin không tự xoá được (xoá sẽ mất luôn quyền quản trị hệ thống).
        abort_unless($user->isOwner() && ! $user->isPlatformAdmin(), 403);

        $data = $request->validate([
            'delete_password' => $user->has_password ? 'required|string' : 'nullable',
            'confirmation' => 'required|string',
        ]);

        // Tài khoản Google chưa đặt mật khẩu: chỉ cần gõ đúng cụm xác nhận (đang đăng nhập = đã qua Google).
        if ($user->has_password && ! Hash::check($data['delete_password'], $user->password)) {
            return back()->withErrors(['delete_password' => 'Mật khẩu không đúng.']);
        }

        if (trim($data['confirmation']) !== 'XOA TAI KHOAN') {
            return back()->withErrors(['confirmation' => 'Bạn cần gõ đúng cụm "XOA TAI KHOAN" (không dấu, viết hoa) để xác nhận.']);
        }

        $deletionService->delete($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Tài khoản và toàn bộ dữ liệu đã được xoá vĩnh viễn.');
    }
}
