<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Services\SchemaUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Khu vực Super Admin — quản lý toàn bộ studio đã đăng ký trên bản Cloud.
 * Chỉ xem SỐ LƯỢNG để vận hành (studio còn hoạt động không, dung lượng ảnh); không mở nội dung đơn, brief, khách hay tiền.
 */
class DashboardController extends Controller
{
    public function index(Request $request, SchemaUpdater $schema): View
    {
        $search = trim((string) $request->query('q'));

        $studios = Studio::query()
            ->with('owner')
            ->withCount('orders')
            ->withMax('orders as last_order_at', 'updated_at')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => Studio::count(),
            'active' => Studio::where('is_active', true)->count(),
            'new_7d' => Studio::where('created_at', '>=', now()->subDays(7))->count(),
            'storage_mb' => round(Studio::sum('storage_bytes') / 1048576, 1),
        ];

        return view('admin.dashboard', compact('studios', 'stats', 'search') + [
            'schemaOk' => $schema->upToDate(),
            'schemaError' => $schema->lastError(),
        ]);
    }

    public function show(int $studio): View
    {
        $studio = Studio::with('owner')->findOrFail($studio);
        $last = $studio->orders()->max('updated_at');

        return view('admin.studios.show', [
            'studio' => $studio,
            'stats' => [
                'orders' => $studio->orders()->count(),
                'open_orders' => $studio->orders()->open()->count(),
                'orders_30d' => $studio->orders()->where('created_at', '>=', now()->subDays(30))->count(),
                'clients' => $studio->clients()->count(),
                'services' => $studio->services()->count(),
                'images' => $studio->images()->count(),
                'last_activity' => $last ? Carbon::parse($last) : null,
            ],
        ]);
    }

    /** Chạy ngay các bản cập nhật database còn thiếu (dùng khi tự cập nhật thất bại hoặc vừa sửa lỗi DB). */
    public function updateSchema(SchemaUpdater $schema): RedirectResponse
    {
        return $schema->update()
            ? back()->with('status', 'Đã cập nhật database lên phiên bản mới nhất.')
            : back()->with('status', 'Chưa cập nhật được database — xem lỗi ở khung màu đỏ (chi tiết trong storage/logs/laravel.log).');
    }

    /** Khoá / mở lại studio — studio bị khoá: chủ không đăng nhập được, trang đặt đơn & theo dõi trả 404. */
    public function toggleActive(int $studio): RedirectResponse
    {
        $studio = Studio::findOrFail($studio);
        $studio->forceFill(['is_active' => ! $studio->is_active])->save();

        return back()->with('status', 'Đã '.($studio->is_active ? 'mở lại' : 'tạm khoá').' studio "'.$studio->name.'".');
    }
}
