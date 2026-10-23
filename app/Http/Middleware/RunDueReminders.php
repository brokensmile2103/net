<?php

namespace App\Http\Middleware;

use App\Services\ReminderService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Cron của người nghèo": sau khi đã trả trang cho người dùng (terminate), thử chạy lượt nhắc khách tự động.
 * ReminderService tự giới hạn tối đa 1 lần / 15 phút và khoá chống chạy trùng — lượt truy cập bình thường gần như không tốn gì.
 */
class RunDueReminders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! file_exists(storage_path('installed.lock'))) {
            return;
        }
        app(ReminderService::class)->runIfDue();
    }
}
