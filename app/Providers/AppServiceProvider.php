<?php

namespace App\Providers;

use App\Models\Order;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Chạy sau Cloudflare / reverse proxy: tin header X-Forwarded-* để nhận đúng https và IP thật của khách.
        $proxies = trim((string) config('net.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
        }

        // Giới hạn tần suất cho trang công khai — mỗi loại 1 khoá riêng (không dùng chung bộ đếm theo IP).
        RateLimiter::for('order-form', fn (Request $r) => Limit::perHour(20)->by('order-form|'.$r->ip()));
        RateLimiter::for('track-view', fn (Request $r) => Limit::perMinute(120)->by('track-view|'.$r->ip()));
        RateLimiter::for('track-action', fn (Request $r) => Limit::perMinute(30)->by('track-action|'.$r->ip().'|'.$r->route('token')));
        RateLimiter::for('google', fn (Request $r) => Limit::perMinute(30)->by('google|'.$r->ip()));
        // Trợ lý AI: chặn bấm liên tục (mỗi lượt tốn hạn mức Gemini của chính studio).
        RateLimiter::for('ai', fn (Request $r) => Limit::perMinute(8)->by('ai|'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('track-respond', fn (Request $r) => Limit::perMinute(10)->by('track-respond|'.$r->ip().'|'.$r->route('token')));

        // Số liệu cho menu điều hướng của layouts.app — tính 1 lần tại đây. Lỗi badge không được làm sập trang.
        View::composer('layouts.app', function ($view) {
            $view->with(['currentStudio' => null, 'newOrdersCount' => 0, 'overdueCount' => 0]);

            $studio = auth()->user()?->studio;
            if (! $studio) {
                return;
            }

            try {
                $view->with([
                    'currentStudio' => $studio,
                    'newOrdersCount' => $studio->orders()->where('status', 'new')->count(),
                    'overdueCount' => $studio->orders()->whereIn('status', Order::openStatuses())
                        ->whereNotNull('due_on')->where('due_on', '<', Carbon::today()->toDateString())->count(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
