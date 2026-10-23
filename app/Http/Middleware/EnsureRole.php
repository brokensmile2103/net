<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        // Super Admin có đủ quyền của chủ studio nên cũng được vào các trang 'owner'.
        $allowed = $user && ($user->role === $role || ($role === 'owner' && $user->isPlatformAdmin()));

        if (! $allowed) {
            abort(403, 'Bạn không có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
