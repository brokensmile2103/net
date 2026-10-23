<?php

if (! function_exists('money')) {
    /**
     * Định dạng số tiền theo chuẩn Việt Nam — dấu CHẤM ngăn cách hàng nghìn,
     * KHÔNG dùng dấu phẩy như number_format() mặc định của PHP (kiểu Mỹ).
     * Dùng hàm này thay cho number_format() ở MỌI nơi hiển thị tiền trong app.
     */
    function money($value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (! function_exists('money_compact')) {
    /**
     * Số tiền RÚT GỌN cho những ô rất hẹp (VD: ô heatmap giờ cao điểm trên điện
     * thoại): 125.000 → "125k", 1.250.000 → "1,3tr", 12.000.000 → "12tr". CHỈ để
     * hiển thị gọn — chỗ nào cần số tiền chính xác vẫn dùng money().
     */
    function money_compact($value): string
    {
        $amount = (float) $value;

        // >= 999.500 làm tròn theo nghìn sẽ ra "1.000k" — chuyển sang đơn vị triệu cho gọn.
        if (abs($amount) >= 999_500) {
            $millions = round($amount / 1_000_000, 1);

            return rtrim(rtrim(number_format($millions, 1, ',', '.'), '0'), ',').'tr';
        }

        if (abs($amount) >= 1_000) {
            return number_format(round($amount / 1_000), 0, ',', '.').'k';
        }

        return number_format(round($amount), 0, ',', '.');
    }
}

if (! function_exists('day_label')) {
    /** "Hôm nay" / "Hôm qua" / "Thứ ba, 06/10/2026" — dùng làm tiêu đề nhóm trong timeline. */
    function day_label(\Illuminate\Support\Carbon|\DateTimeInterface $date): string
    {
        $date = \Illuminate\Support\Carbon::instance($date)->startOfDay();

        if ($date->isToday()) {
            return 'Hôm nay';
        }

        if ($date->isYesterday()) {
            return 'Hôm qua';
        }

        $weekdays = [1 => 'Thứ hai', 2 => 'Thứ ba', 3 => 'Thứ tư', 4 => 'Thứ năm', 5 => 'Thứ sáu', 6 => 'Thứ bảy', 7 => 'Chủ nhật'];

        return $weekdays[$date->dayOfWeekIso].', '.$date->format('d/m/Y');
    }
}

if (! function_exists('ago')) {
    /** "2 ngày trước" / "hôm nay" — mức độ gần đây của 1 mốc thời gian. */
    function ago($date): string
    {
        if (! $date) {
            return 'chưa có';
        }

        $date = \Illuminate\Support\Carbon::parse($date);
        $days = (int) $date->copy()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::today());

        return match (true) {
            $days <= 0 => 'hôm nay',
            $days === 1 => 'hôm qua',
            default => $days.' ngày trước',
        };
    }
}

if (! function_exists('input_number')) {
    /**
     * Giá trị để ĐIỀN SẴN vào <input type="number">: số "sạch" kiểu máy (dấu chấm thập phân,
     * không phân cách nghìn, bỏ số 0 thừa). KHÔNG dùng quantity()/money() cho value của ô số —
     * "1.500" (1500 kiểu VN) sẽ bị trình duyệt hiểu thành 1,5 và "2,5" bị coi là không hợp lệ.
     */
    function input_number($value, int $maxDecimals = 3): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $s = number_format(round((float) $value, $maxDecimals), $maxDecimals, '.', '');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}

if (! function_exists('parse_date')) {
    /**
     * Đọc ngày từ query string (Y-m-d) an toàn: chuỗi rác/sai định dạng trả về $default
     * thay vì ném exception (lỗi 500) như Carbon::parse().
     */
    function parse_date($value, ?\Illuminate\Support\Carbon $default = null): ?\Illuminate\Support\Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $default;
        }

        try {
            $date = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return $default;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : $default;
    }
}

if (! function_exists('normalize_phone')) {
    /**
     * Chuẩn hoá số điện thoại dùng để ĐĂNG NHẬP: bỏ khoảng trắng, dấu chấm, gạch, ngoặc; "+84" → "0".
     * "0912 345 678", "0912.345.678", "+84912345678" đều thành "0912345678" — tránh 1 người có 2 tài khoản
     * hoặc gõ khác lúc đăng ký là không đăng nhập được.
     */
    function normalize_phone(?string $phone): string
    {
        $phone = trim((string) $phone);
        $plus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone);

        if ($plus && str_starts_with($digits, '84')) {
            return '0'.substr($digits, 2);
        }

        return ($plus ? '+' : '').$digits;
    }
}

if (! function_exists('csv_safe')) {
    /** Chống "CSV injection": ô bắt đầu bằng = + - @ bị Excel hiểu là công thức. */
    function csv_safe($value)
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}

if (! function_exists('parse_month')) {
    /** Đọc tháng dạng YYYY-MM từ query string an toàn (sai định dạng → $default). */
    function parse_month($value, ?\Illuminate\Support\Carbon $default = null): \Illuminate\Support\Carbon
    {
        $default ??= \Illuminate\Support\Carbon::today()->startOfMonth();

        if (! is_string($value) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value)) {
            return $default;
        }

        return \Illuminate\Support\Carbon::createFromFormat('!Y-m', $value)->startOfMonth();
    }
}

if (! function_exists('vietqr_url')) {
    /** Ảnh mã VietQR (dịch vụ công khai img.vietqr.io) — khách quét bằng app ngân hàng là điền sẵn số tiền + nội dung. */
    function vietqr_url(?string $bankCode, ?string $account, float $amount = 0, string $memo = '', ?string $holder = null): ?string
    {
        if (! $bankCode || ! $account) {
            return null;
        }

        $query = array_filter([
            'amount' => $amount > 0 ? (int) round($amount) : null,
            'addInfo' => $memo ?: null,
            'accountName' => $holder ?: null,
        ]);

        return 'https://img.vietqr.io/image/'.rawurlencode(trim($bankCode)).'-'.rawurlencode(preg_replace('/\s+/', '', $account)).'-compact2.png'
            .($query ? '?'.http_build_query($query) : '');
    }
}


if (! function_exists('tone_badge')) {
    /** Class Tailwind (liệt kê ĐẦY ĐỦ để Tailwind quét được) cho huy hiệu theo "tone" trong config/net.php. */
    function tone_badge(?string $tone): string
    {
        return [
            'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'brand' => 'bg-[var(--accent-soft)] text-[var(--accent-ink)] ring-[var(--accent-line)]',
            'orange' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'red' => 'bg-red-50 text-red-700 ring-red-200',
        ][$tone] ?? 'bg-stone-100 text-stone-600 ring-stone-200';
    }
}

if (! function_exists('tone_dot')) {
    function tone_dot(?string $tone): string
    {
        return [
            'sky' => 'bg-sky-500', 'violet' => 'bg-violet-500', 'amber' => 'bg-amber-500', 'brand' => 'bg-[var(--accent)]',
            'orange' => 'bg-orange-500', 'emerald' => 'bg-emerald-500', 'red' => 'bg-red-500',
        ][$tone] ?? 'bg-stone-400';
    }
}

if (! function_exists('link_host_label')) {
    /** Tên dễ đọc cho 1 đường link: "Google Drive", "Figma", "Canva"... hoặc tên miền. */
    function link_host_label(?string $url): string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        return match (true) {
            str_contains($host, 'drive.google.') || str_contains($host, 'docs.google.') => 'Google Drive',
            str_contains($host, 'figma.com') => 'Figma',
            str_contains($host, 'canva.') => 'Canva',
            str_contains($host, 'dropbox.') => 'Dropbox',
            str_contains($host, 'onedrive.') || str_contains($host, 'sharepoint.') || str_contains($host, '1drv.ms') => 'OneDrive',
            str_contains($host, 'behance.') => 'Behance',
            str_contains($host, 'pinterest.') || str_contains($host, 'pin.it') => 'Pinterest',
            $host !== '' => $host,
            default => 'Đường link',
        };
    }
}

if (! function_exists('link_icon')) {
    function link_icon(?string $url): string
    {
        return match (link_host_label($url)) {
            'Google Drive' => 'fa-brands fa-google-drive',
            'Figma' => 'fa-brands fa-figma',
            'Dropbox' => 'fa-brands fa-dropbox',
            'Behance' => 'fa-brands fa-behance',
            'Pinterest' => 'fa-brands fa-pinterest',
            default => 'fa-solid fa-link',
        };
    }
}

if (! function_exists('is_drive_link')) {
    function is_drive_link(?string $url): bool
    {
        return link_host_label($url) === 'Google Drive';
    }
}

if (! function_exists('client_message')) {
    /**
     * Tin nhắn gửi khách (dán vào Zalo/Messenger) theo giai đoạn hiện tại của đơn, kèm link theo dõi.
     * Không gửi tự động — freelancer chủ động bấm "Sao chép" để giữ giọng của chính mình.
     */
    function client_message(\App\Models\Order $order): string
    {
        $studio = $order->studio;
        $client = $order->client;
        $hi = 'Chào '.($client?->name ?: 'bạn').',';
        $link = $order->trackingUrl();

        $body = match ($order->status) {
            'new' => 'Mình đã nhận yêu cầu "'.$order->title.'". Mình sẽ xem brief và gửi báo giá sớm nhé.',
            'quoted' => 'Mình gửi báo giá cho "'.$order->title.'": tổng '.money($order->total_amount).'đ'
                .($order->depositAmount() > 0 ? ', cọc '.$order->deposit_percent.'% là '.money($order->depositAmount()).'đ' : '')
                .'. Bạn xem chi tiết và bấm "Đồng ý báo giá" ở link bên dưới giúp mình nhé.',
            'in_progress' => 'Mình đang bắt tay vào thiết kế "'.$order->title.'"'.($order->due_on ? ', dự kiến gửi bạn trước '.$order->due_on->format('d/m') : '').'.',
            'review' => 'Mình đã gửi phương án '.($order->latestRound()?->label() ?? '').' cho "'.$order->title.'". Bạn xem, chọn phương án ưng ý và góp ý trực tiếp lên hình ở link bên dưới nhé.',
            'revising' => 'Mình đã nhận góp ý và đang chỉnh sửa "'.$order->title.'". Xong mình gửi bạn xem ngay.',
            'approved' => 'Cảm ơn bạn đã duyệt "'.$order->title.'"!'.($order->balance() > 0 ? ' '.((float) $order->paid_amount > 0 ? 'Phần còn lại' : 'Chi phí').' là '.money($order->balance()).'đ, thanh toán xong mình gửi file gốc ngay.' : ' Mình đang chuẩn bị file bàn giao.'),
            'delivered' => 'File bàn giao "'.$order->title.'" đã sẵn sàng trong link bên dưới. Cảm ơn bạn đã tin tưởng!',
            'cancelled' => 'Đơn "'.$order->title.'" đã được huỷ.'.($order->cancel_reason ? ' Lý do: '.$order->cancel_reason.'.' : ''),
            default => 'Cập nhật đơn "'.$order->title.'".',
        };

        return trim($hi."\n".$body."\n\nTheo dõi đơn ".$order->code.': '.$link."\n— ".$studio?->name);
    }
}
