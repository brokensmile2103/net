<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cập nhật cấu trúc database sau khi upload bản mới — không cần SSH.
 *
 * Nét được cài qua trình duyệt (kiểu WordPress) trên hosting thường không có dòng lệnh, nên sau khi chép code mới lên,
 * KHÔNG ai chạy `php artisan migrate`. Code mới truy vấn cột chưa tồn tại → lỗi 500 (đây là nguyên nhân hay gặp nhất
 * của lỗi "bấm đăng nhập Google là 500"). Lớp này so migration mới nhất trong code với dấu đã ghi ở
 * storage/schema.version; lệch thì chạy migrate (có khoá để 2 request không chạy cùng lúc).
 */
class SchemaUpdater
{
    private const ERROR_KEY = 'net:schema-update-error';

    private const RETRY_KEY = 'net:schema-update-retry';

    public function stampPath(): string
    {
        return storage_path('schema.version');
    }

    /** Tên file migration mới nhất trong code (tên có tiền tố ngày giờ nên sắp xếp chữ = sắp xếp thời gian). */
    public function latestMigration(): string
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        if ($files === []) {
            return '';
        }

        sort($files);

        return basename(end($files), '.php');
    }

    public function upToDate(): bool
    {
        $stamp = is_file($this->stampPath()) ? trim((string) @file_get_contents($this->stampPath())) : '';

        return $stamp !== '' && $stamp === $this->latestMigration();
    }

    /** Lỗi của lần cập nhật gần nhất (hiện cho Super Admin), null nếu không có. */
    public function lastError(): ?string
    {
        return Cache::get(self::ERROR_KEY);
    }

    /**
     * Gọi ở mỗi request: chỉ làm việc khi có migration mới. Lỗi thì ghi log và thử lại sau 5 phút
     * (không chạy migrate liên tục ở mọi request khi database đang có vấn đề).
     */
    public function updateIfNeeded(): void
    {
        if ($this->upToDate() || ! Cache::add(self::RETRY_KEY, 1, 300)) {
            return;
        }

        $this->update();
    }

    /** Chạy migrate ngay. true = thành công. */
    public function update(): bool
    {
        $lock = Cache::lock('net:schema-update', 120);
        if (! $lock->get()) {
            return false; // request khác đang cập nhật
        }

        try {
            $exit = Artisan::call('migrate', ['--force' => true]);
            if ($exit !== 0) {
                throw new \RuntimeException('migrate kết thúc với mã '.$exit.': '.trim(Artisan::output()));
            }

            // Dọn cache view/route/config cũ còn trỏ tới code trước khi cập nhật.
            foreach (['view:clear', 'route:clear', 'config:clear'] as $command) {
                try {
                    Artisan::call($command);
                } catch (\Throwable $e) {
                    Log::warning('Không chạy được '.$command.': '.$e->getMessage());
                }
            }

            @file_put_contents($this->stampPath(), $this->latestMigration());
            Cache::forget(self::ERROR_KEY);
            Cache::forget(self::RETRY_KEY);
            Log::info('Đã cập nhật cấu trúc database tới '.$this->latestMigration());

            return true;
        } catch (\Throwable $e) {
            report($e);
            Cache::forever(self::ERROR_KEY, $e->getMessage());

            return false;
        } finally {
            $lock->release();
        }
    }
}
