<?php

namespace App\Services;

use App\Models\Studio;

/** Dữ liệu khởi đầu cho studio mới: vài dịch vụ mẫu để trang đặt đơn có nội dung ngay (sửa/xoá thoải mái). */
class StudioSetup
{
    public function seedServices(Studio $studio): void
    {
        $samples = [
            [
                'name' => 'Thiết kế logo',
                'description' => '2–3 hướng ý tưởng ban đầu, chọn 1 hướng để hoàn thiện. Bàn giao file AI, SVG, PNG nền trong.',
                'price' => 3000000, 'price_is_from' => true, 'duration_days' => 7, 'revision_rounds' => 3,
                'brief_hint' => 'Tên thương hiệu, ngành nghề, khách hàng mục tiêu, vài logo bạn thích (dán link Pinterest/Behance), màu muốn dùng hoặc muốn tránh.',
            ],
            [
                'name' => 'Ấn phẩm mạng xã hội',
                'description' => 'Bài đăng, ảnh bìa, banner quảng cáo theo kích thước từng nền tảng.',
                'price' => 400000, 'price_is_from' => true, 'duration_days' => 2, 'revision_rounds' => 2,
                'brief_hint' => 'Nền tảng đăng (Facebook, Instagram, TikTok...), kích thước, nội dung chữ, ảnh sản phẩm (gửi link Drive), hạn đăng bài.',
            ],
            [
                'name' => 'Bộ nhận diện thương hiệu',
                'description' => 'Logo, bảng màu, font chữ, danh thiếp, bộ ứng dụng cơ bản và tài liệu hướng dẫn sử dụng.',
                'price' => null, 'price_is_from' => false, 'duration_days' => 21, 'revision_rounds' => 3,
                'brief_hint' => 'Câu chuyện thương hiệu, sản phẩm/dịch vụ, đối thủ, các ấn phẩm cần dùng ngay.',
            ],
        ];

        foreach ($samples as $i => $row) {
            $studio->services()->create($row + ['sort_order' => $i, 'is_public' => true]);
        }
    }
}
