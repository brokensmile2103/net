<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn truy cập app khi chưa cài đặt xong, và chặn quay lại /install sau khi đã cài.
 * File cờ storage/installed.lock là nguồn sự thật duy nhất — không dựa vào session/DB
 * vì DB có thể chưa tồn tại ở bước đầu cài đặt.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = file_exists(storage_path('installed.lock'));
        $isInstallRoute = $request->routeIs('install.*');

        if (! $installed && ! $isInstallRoute) {
            return redirect()->route('install.welcome');
        }

        if ($installed && $isInstallRoute) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
