<?php

// Nét không có tác vụ chạy định kỳ bắt buộc. Thêm lịch chạy tại đây nếu cần
// (bật bằng cron: * * * * * cd /đường/dẫn/dự/án && php artisan schedule:run >> /dev/null 2>&1).

use App\Services\ReminderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Nhắc khách qua email. Không có cron Nét vẫn tự chạy kèm lượt truy cập web; có cron thì chạy đều đặn hơn.
Artisan::command('net:nhac-khach', function (ReminderService $reminders) {
    if (! ReminderService::mailReady()) {
        $this->warn('Máy chủ chưa cấu hình gửi email (MAIL_MAILER / RESEND_API_KEY) — bỏ qua.');

        return;
    }
    $this->info('Đã gửi '.$reminders->run(100).' email nhắc khách.');
})->purpose('Gửi email nhắc khách (phương án chờ xem, báo giá chờ đồng ý, còn nợ)');

Schedule::command('net:nhac-khach')->hourly()->withoutOverlapping();
