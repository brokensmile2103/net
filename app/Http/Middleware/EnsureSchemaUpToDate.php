<?php

namespace App\Http\Middleware;

use App\Services\SchemaUpdater;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sau khi upload bản Nét mới, request đầu tiên tự chạy các migration còn thiếu (xem SchemaUpdater).
 * Chỉ tốn 1 lần đọc thư mục migrations + 1 file nhỏ mỗi request khi đã cập nhật xong.
 */
class EnsureSchemaUpToDate
{
    public function __construct(private SchemaUpdater $updater) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('net.auto_migrate') && ! app()->runningUnitTests() && ! $request->routeIs('install.*')
            && file_exists(storage_path('installed.lock'))) {
            try {
                $this->updater->updateIfNeeded();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
