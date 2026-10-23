<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tìm nhanh cho bảng lệnh (Ctrl + K): đơn theo mã / tên dự án / khách, khách theo tên / SĐT / email / Facebook.
 * Chỉ trong studio của người đang đăng nhập.
 */
class QuickSearchController extends Controller
{
    use ResolvesStudio;

    public function __invoke(Request $request): JsonResponse
    {
        $studio = $this->studio($request);
        $q = mb_substr(trim((string) $request->query('q')), 0, 100);
        if ($q === '') {
            return response()->json(['orders' => [], 'clients' => []]);
        }
        $like = '%'.addcslashes($q, '%_\\').'%';

        $orders = $studio->orders()->with('client')
            ->where(fn ($s) => $s->where('code', 'like', $like)->orWhere('title', 'like', $like)
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like)))
            // Mã đơn khớp chính xác lên đầu, rồi đơn đang chạy, rồi mới nhất.
            ->orderByRaw('CASE WHEN LOWER(code) = ? THEN 0 ELSE 1 END', [mb_strtolower($q)])
            ->orderByRaw("CASE WHEN status IN ('delivered', 'cancelled') THEN 1 ELSE 0 END")
            ->orderByDesc('id')->limit(7)->get()
            ->map(fn ($o) => [
                'code' => $o->code,
                'title' => $o->title,
                'client' => $o->client?->name,
                'status' => $o->statusLabel(),
                'url' => route('orders.show', $o->id),
                'cover' => $o->coverUrl(),
            ]);

        $clients = $studio->clients()
            ->where(fn ($s) => $s->where('name', 'like', $like)->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)->orWhere('facebook', 'like', $like))
            ->orderBy('name')->limit(5)->get()
            ->map(fn ($c) => ['name' => $c->name, 'detail' => $c->phone ?: $c->email, 'url' => route('clients.show', $c->id)]);

        return response()->json(['orders' => $orders, 'clients' => $clients]);
    }
}
