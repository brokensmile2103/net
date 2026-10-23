<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Studio;
use Illuminate\Http\Request;

/**
 * Studio của người đang đăng nhập (mỗi freelancer đúng 1 studio). KHÔNG BAO GIỜ nhận studio_id từ client —
 * mọi truy vấn theo id (đơn, khách, dịch vụ...) phải đi qua $studio->relation()->findOrFail($id)
 * để không đọc chéo dữ liệu giữa các studio.
 */
trait ResolvesStudio
{
    protected function studio(Request $request): Studio
    {
        $user = $request->user();
        // Super Admin của bản cài cũ chưa có studio riêng → tạo tự động.
        $studio = $user?->studio ?? ($user ? app(\App\Services\StudioProvisioner::class)->ensureFor($user) : null);

        abort_unless($studio, 403, 'Tài khoản chưa có studio.');
        abort_unless($studio->is_active, 403, 'Studio đang tạm khoá. Vui lòng liên hệ hỗ trợ.');

        return $studio;
    }

    /** Chỉ cho quay lại URL nội bộ (chống open-redirect qua trường return_to). */
    protected function safeReturn(Request $request, string $default): string
    {
        $to = (string) $request->input('return_to', '');

        // "/\evil.com" được trình duyệt hiểu như "//evil.com" → chặn cả dấu gạch ngược.
        return ($to !== '' && str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\')) ? $to : $default;
    }
}
