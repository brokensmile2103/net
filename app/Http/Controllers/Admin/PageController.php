<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CLOUD — Super Admin sửa nội dung 3 trang tĩnh, lưu thẳng vào database, không cần sửa code/deploy lại. */
class PageController extends Controller
{
    public function edit(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'content' => 'nullable|string',
            'meta_description' => 'nullable|string|max:160',
        ]);

        $data['content'] = $this->sanitizeHtml($data['content'] ?? '');

        $page->update($data);

        return back()->with('status', 'Đã lưu nội dung trang "'.$page->title.'".');
    }

    /**
     * Giới hạn thẻ HTML được phép lưu, kể cả với chính Super Admin — nguyên
     * tắc phòng thủ theo chiều sâu: nội dung trang này hiển thị RAW HTML cho
     * MỌI khách truy cập công khai (kể cả chưa đăng nhập), nên nếu tài khoản
     * Super Admin từng bị lộ mật khẩu (phishing, dùng lại mật khẩu cũ...),
     * không được để kẻ tấn công chèn được JavaScript ảnh hưởng tới TẤT CẢ
     * người xem trang. strip_tags() chỉ lọc TÊN THẺ, không lọc thuộc tính —
     * nên phải tự loại bỏ thêm thuộc tính sự kiện (onclick, onerror...) và
     * scheme nguy hiểm (javascript:, vbscript:, data:) trong href/src, vì
     * các đường này vẫn chạy được JS ngay cả trong các thẻ nằm trong danh
     * sách cho phép như <a>.
     */
    private function sanitizeHtml(string $html): string
    {
        $allowedTags = '<h3><h4><p><ul><ol><li><strong><b><em><i><a><br><span>';
        $clean = strip_tags($html, $allowedTags);

        // Lặp lại tới khi không còn thay đổi — 1 lượt duy nhất có thể để sót
        // thuộc tính sự kiện nếu chuỗi gốc có cấu trúc lồng nhau bất thường.
        do {
            $before = $clean;
            $clean = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/i', '', $clean);
            $clean = preg_replace('/\son\w+\s*=\s*[^\s>]+/i', '', $clean);
        } while ($clean !== $before);

        // Chặn href/src trỏ scheme nguy hiểm. Giải mã HTML entity + bỏ ký tự
        // điều khiển (tab/xuống dòng...) TRƯỚC khi so khớp, vì trình duyệt
        // vẫn bỏ qua các ký tự này khi đọc scheme — "java&#9;script:" hay
        // "java\tscript:" đều là chiêu né 1 bộ lọc chỉ so khớp chuỗi liền
        // mạch "javascript:". So khớp không phân biệt hoa/thường.
        $clean = preg_replace_callback(
            '/(href|src)\s*=\s*(["\'])(.*?)\2/i',
            function (array $m): string {
                $decoded = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5);
                $normalized = strtolower(preg_replace('/[\x00-\x1F\s]+/', '', $decoded));

                foreach (['javascript:', 'vbscript:', 'data:'] as $scheme) {
                    if (str_starts_with($normalized, $scheme)) {
                        return $m[1].'="#"';
                    }
                }

                return $m[0];
            },
            $clean
        );

        return $clean;
    }
}
