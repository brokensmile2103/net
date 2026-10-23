<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Super Admin — cài đặt cấp nền tảng (hiện có: cho phép/đóng đăng ký công khai). */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['registrationOpen' => PlatformSetting::registrationOpen()]);
    }

    public function update(Request $request): RedirectResponse
    {
        PlatformSetting::put('registration_open', $request->boolean('registration_open') ? '1' : '0');

        return back()->with('status', 'Đã lưu cài đặt.');
    }
}
