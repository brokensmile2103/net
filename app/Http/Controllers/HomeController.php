<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Trang chủ công khai (giới thiệu Nét). Người đã đăng nhập được đưa thẳng vào khu vực làm việc.
 */
class HomeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('today'));
        }

        return view('home');
    }
}
