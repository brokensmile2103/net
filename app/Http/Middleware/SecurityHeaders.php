<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set các HTTP header bảo mật CHUẨN, không phụ thuộc cấu hình Nginx/Apache
 * phía server (nhiều VPS tự cài đặt thủ công không có sẵn các header này) —
 * an toàn tuyệt đối, không ảnh hưởng chức năng, nên áp dụng cho MỌI response.
 *
 * Không thêm Content-Security-Policy ở đây: CSP cần liệt kê chính xác mọi
 * nguồn script/style/font đang dùng (Google Fonts, FontAwesome, Vite dev
 * server...), rất dễ tự làm gãy trang nếu áp dụng vội mà không kiểm thử kỹ
 * từng trang — để dành làm riêng, kiểm tra cẩn thận, không vá kèm ở đây.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Không cho browser tự đoán content-type khác Content-Type server trả
        // về — chặn 1 lớp tấn công XSS qua file upload bị hiểu nhầm là HTML/JS.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Chặn clickjacking — không trang nào được nhúng Nét vào <iframe>,
        // kể cả từ chính domain khác của mình (đăng nhập không có lý do gì
        // cần hiển thị trong iframe).
        $response->headers->set('X-Frame-Options', 'DENY');

        // Không gửi URL đầy đủ (có thể chứa thông tin nhạy cảm trong query
        // string) sang site khác khi người dùng click link ra ngoài — chỉ gửi
        // origin khi qua domain khác, gửi đầy đủ khi cùng domain.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Tắt hẳn các API trình duyệt không dùng tới — giảm bề mặt tấn công
        // nếu 1 đoạn script lạ (qua lỗ hổng khác) cố truy cập camera/mic/định vị.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
