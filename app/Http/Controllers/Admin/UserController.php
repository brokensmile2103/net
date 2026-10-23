<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Super Admin quản lý toàn bộ tài khoản — chủ yếu để hỗ trợ khi freelancer quên mật khẩu
 * mà chưa điền email khôi phục.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('studio')
            ->when($request->query('q'), function ($q, $search) {
                $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /** Đổi mật khẩu cho BẤT KỲ người dùng nào — dùng khi họ quên mật khẩu và không có ai gần đó xử lý được. */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isPlatformAdmin() && $user->id !== $request->user()->id, 403, 'Không thể đổi mật khẩu của Super Admin khác.');

        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->forceFill(['password' => Hash::make($data['password']), 'has_password' => true])->save();

        return back()->with('status', 'Đã đổi mật khẩu cho '.$user->name.'.');
    }

    /** Khoá/mở 1 tài khoản (đăng nhập). Trang đặt đơn của studio vẫn chạy — muốn tắt hẳn thì khoá studio ở Danh sách studio. */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Không thể tự khoá chính mình.');

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('status', $user->is_active ? 'Đã mở lại tài khoản '.$user->name.'.' : 'Đã khoá tài khoản '.$user->name.'.');
    }
}
