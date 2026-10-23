<?php

/**
 * Cấu hình nghiệp vụ của Nét (quản lý đơn thiết kế cho freelancer). Đổi ở đây là đổi toàn app.
 */
return [

    /*
     * Các giai đoạn của 1 đơn, theo đúng thứ tự trên bảng việc.
     *  - client_label: chữ khách thấy trên trang theo dõi (thân thiện hơn chữ nội bộ).
     *  - board: có hiện thành 1 cột trên Bảng việc không.
     *  - open: đơn còn đang chạy (tính vào "đang làm", hạn chót, nhắc trễ hạn).
     */
    'statuses' => [
        'new' => ['label' => 'Chờ nhận', 'client_label' => 'Đã gửi yêu cầu', 'icon' => 'fa-inbox', 'tone' => 'sky', 'board' => true, 'open' => true],
        'quoted' => ['label' => 'Đã báo giá', 'client_label' => 'Chờ xác nhận báo giá', 'icon' => 'fa-file-signature', 'tone' => 'violet', 'board' => true, 'open' => true],
        'in_progress' => ['label' => 'Đang làm', 'client_label' => 'Đang thiết kế', 'icon' => 'fa-pen-nib', 'tone' => 'amber', 'board' => true, 'open' => true],
        'review' => ['label' => 'Chờ khách xem', 'client_label' => 'Có phương án mới', 'icon' => 'fa-eye', 'tone' => 'brand', 'board' => true, 'open' => true],
        'revising' => ['label' => 'Đang sửa', 'client_label' => 'Đang chỉnh sửa theo góp ý', 'icon' => 'fa-rotate', 'tone' => 'orange', 'board' => true, 'open' => true],
        'approved' => ['label' => 'Đã duyệt', 'client_label' => 'Đã duyệt, chuẩn bị bàn giao', 'icon' => 'fa-circle-check', 'tone' => 'emerald', 'board' => true, 'open' => true],
        'delivered' => ['label' => 'Đã bàn giao', 'client_label' => 'Đã bàn giao', 'icon' => 'fa-box-archive', 'tone' => 'neutral', 'board' => true, 'open' => false],
        'cancelled' => ['label' => 'Đã huỷ', 'client_label' => 'Đã huỷ', 'icon' => 'fa-ban', 'tone' => 'red', 'board' => false, 'open' => false],
    ],

    // Các mốc khách thấy trên thanh tiến độ (trang theo dõi). Mỗi mốc gom 1 hoặc vài giai đoạn.
    'client_steps' => [
        ['label' => 'Gửi yêu cầu', 'statuses' => ['new']],
        ['label' => 'Báo giá', 'statuses' => ['quoted']],
        ['label' => 'Thiết kế', 'statuses' => ['in_progress', 'review', 'revising']],
        ['label' => 'Duyệt', 'statuses' => ['approved']],
        ['label' => 'Bàn giao', 'statuses' => ['delivered']],
    ],

    'priorities' => [
        'normal' => 'Bình thường',
        'urgent' => 'Gấp',
    ],

    // Loại link Google Drive gắn với đơn.
    'link_kinds' => [
        'reference' => ['label' => 'Tài liệu, tham khảo', 'icon' => 'fa-folder-open'],
        'source' => ['label' => 'File bàn giao', 'icon' => 'fa-box-archive'],
        'other' => ['label' => 'Link khác', 'icon' => 'fa-link'],
    ],

    // Khoản tiền trên báo giá. Giảm giá nhập số dương, hệ thống tự trừ.
    'charge_kinds' => [
        'base' => 'Phí thiết kế',
        'extra' => 'Phụ phí',
        'revision' => 'Phí sửa thêm',
        'rush' => 'Phí làm gấp',
        'discount' => 'Giảm giá',
    ],

    'payment_methods' => [
        'chuyen_khoan' => 'Chuyển khoản',
        'tien_mat' => 'Tiền mặt',
    ],

    // Mặc định cho studio mới (chủ studio sửa được ở Cài đặt, từng dịch vụ/đơn sửa riêng được).
    'defaults' => [
        'deposit_percent' => 50,
        'revision_rounds' => 2,
    ],

    // Ảnh: tự thu nhỏ & nén WebP trước khi lưu — file gốc chất lượng cao để ở Google Drive.
    'images' => [
        'max_upload_kb' => 15360,       // file người dùng chọn tối đa 15 MB
        'preview_max_px' => 1800,       // cạnh dài ảnh xem trước phương án
        'cover_max_px' => 1200,         // cạnh dài ảnh bìa đơn
        'avatar_px' => 320,             // logo/ảnh đại diện studio (vuông)
        'quality' => 80,
        'per_proposal' => 8,            // số ảnh tối đa cho 1 phương án
    ],

    // Dung lượng ảnh tối đa mỗi studio (MB).
    'storage_quota_mb' => (int) env('NET_STORAGE_QUOTA_MB', 500),

    // Đơn "Chờ khách xem" quá số ngày này thì nhắc ở mục Hôm nay.
    'waiting_reminder_days' => 3,

    // Tự động nhắc khách qua email (Cài đặt → Nhắc khách tự động). Chỉ gửi cho khách có email "có vẻ thật" (App\Support\EmailCheck).
    'reminders' => [
        'auto_run' => env('NET_REMINDERS_AUTO', true),   // chạy kèm lượt truy cập web (không cần cron), tối đa 1 lần / every_minutes
        'every_minutes' => 15,
        'batch' => 20,                                    // số email tối đa mỗi lượt
        'hours' => [8, 20],                               // chỉ gửi trong khung giờ này (giờ máy chủ, mặc định Asia/Ho_Chi_Minh)
        'check_dns' => env('NET_EMAIL_CHECK_DNS', true),  // kiểm tra tên miền có nhận được thư không
        'kinds' => [
            'review' => ['label' => 'Phương án chờ khách xem', 'hint' => 'Đơn ở “Chờ khách xem” quá N ngày'],
            'quote' => ['label' => 'Báo giá chờ khách đồng ý', 'hint' => 'Đã gửi báo giá quá N ngày mà khách chưa bấm đồng ý'],
            'payment' => ['label' => 'Nhắc thanh toán phần còn lại', 'hint' => 'Đã duyệt / đã bàn giao mà còn nợ quá N ngày'],
        ],
    ],

    // Hẹn gọi tư vấn trên trang đặt đơn (Cài đặt → Hẹn gọi tư vấn, mặc định TẮT).
    'consult' => [
        'durations' => [15, 20, 30, 45, 60],
        'channels' => [
            'zalo' => ['label' => 'Gọi Zalo', 'icon' => 'fa-comment-dots'],
            'phone' => ['label' => 'Gọi điện thoại', 'icon' => 'fa-phone'],
            'meet' => ['label' => 'Google Meet', 'icon' => 'fa-video'],
        ],
        'weekdays' => [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'],
        'max_ranges' => 3,
    ],

    // Bảng việc.
    'board' => [
        'page_size' => 40,                 // số thẻ tải mỗi lần ở mỗi cột ("Tải thêm" để xem tiếp)
        'stale_days' => 5,                 // đơn nằm yên ở 1 giai đoạn từng này ngày → gắn nhãn "đứng yên" (Chờ khách xem dùng waiting_reminder_days)
        'delivered_days' => [7, 30, 90],   // cột "Đã bàn giao" hiện đơn bàn giao trong bao nhiêu ngày gần đây
        'views' => [
            'cards' => ['label' => 'Thẻ', 'icon' => 'fa-table-cells-large', 'hint' => 'Ảnh bìa lớn'],
            'compact' => ['label' => 'Gọn', 'icon' => 'fa-table-list', 'hint' => 'Bìa thu nhỏ cạnh mã đơn, tên dự án, khách'],
            'rows' => ['label' => 'Dòng', 'icon' => 'fa-bars', 'hint' => 'Mỗi đơn một dòng — xem được nhiều nhất'],
        ],
        'sorts' => [
            'priority' => 'Ưu tiên & hạn chót',
            'due' => 'Hạn chót gần nhất',
            'stalled' => 'Đứng yên lâu nhất',
            'recent' => 'Mới cập nhật',
            'newest' => 'Mới tạo',
            'amount' => 'Giá trị cao nhất',
        ],
        'filters' => [
            'gap' => 'Gấp',
            'tre-han' => 'Trễ hạn',
            'sap-han' => 'Hạn trong 7 ngày',
            'con-no' => 'Còn nợ',
            'dung-yen' => 'Đứng yên lâu',
            'chua-han' => 'Chưa đặt hạn',
        ],
    ],

    // Màu nhấn — đặt tên theo bảng màu in ấn quen thuộc với dân thiết kế.
    'accent_colors' => [
        'magenta' => ['label' => 'Magenta', 'hex' => '#c8106e'],
        'cyan' => ['label' => 'Cyan', 'hex' => '#0a7fb8'],
        'ink' => ['label' => 'Mực đen', 'hex' => '#1f2128'],
        'green' => ['label' => 'Lục', 'hex' => '#14804a'],
        'orange' => ['label' => 'Cam', 'hex' => '#d4540f'],
        'violet' => ['label' => 'Tím', 'hex' => '#6440d0'],
    ],

    /*
     * Trợ lý AI (Google Gemini) — mỗi studio tự dán API key miễn phí của mình (aistudio.google.com/apikey)
     * và tự chọn mô hình ở Cài đặt → Trợ lý AI. NET_AI_MODELS chỉ là danh sách MẶC ĐỊNH cho studio chưa chọn.
     * Thử lần lượt từ trên xuống: hết lượt / quá tải / bị Google khai tử thì tự chuyển mô hình sau.
     */
    'ai' => [
        'default_models' => array_values(array_filter(array_map('trim', explode(',', (string) env('NET_AI_MODELS', 'gemini-3.5-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite'))))),
        'max_models' => 3,
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'timeout' => 40,          // giây cho 1 lượt gọi
        'total_budget' => 75,     // tổng số giây tối đa cho cả chuỗi dự phòng (hosting thường giới hạn PHP 30–120 giây)
        'reset_timezone' => 'America/Los_Angeles', // Google reset hạn mức ngày lúc 0h giờ Thái Bình Dương
    ],

    /*
     * Proxy tin cậy (Cloudflare, Nginx reverse proxy, load balancer của hosting…). Đặt TRUSTED_PROXIES=* khi website
     * chạy sau proxy — nếu không, Laravel tưởng mọi request là http:// và gửi Google redirect_uri sai (lỗi
     * redirect_uri_mismatch), link trong email cũng thành http://. Để trống khi PHP nhận request trực tiếp.
     */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Tự cập nhật cấu trúc database khi phát hiện bản cập nhật mới (không cần SSH). Xem App\Services\SchemaUpdater.
    'auto_migrate' => (bool) env('NET_AUTO_MIGRATE', true),

    /*
     * Mẫu giao diện trang đặt đơn + trang theo dõi của khách (chủ studio chọn ở Cài đặt → Trang đặt đơn).
     * Mỗi mẫu = 1 bộ CSS trong resources/css/themes.css (theo [data-theme]) + 1 phần đầu trang ở
     * resources/views/public/heroes/{key}.blade.php. Thêm mẫu mới: thêm 3 chỗ đó.
     */
    'page_themes' => [
        'paper' => ['label' => 'Tối giản', 'desc' => 'Sạch, nhẹ, để tác phẩm tự nói.'],
        'artboard' => ['label' => 'Bàn vẽ', 'desc' => 'Như đang mở file trong phần mềm thiết kế: thước kẻ, khung artboard, khung chọn.'],
        'riso' => ['label' => 'In Risograph', 'desc' => 'Hai màu mực in chồng, hạt giấy, lệch khuôn — cho ai mê ấn phẩm in.'],
        'swiss' => ['label' => 'Lưới Thuỵ Sĩ', 'desc' => 'Chữ thật lớn, lưới chặt, khối màu dứt khoát.'],
        'sketch' => ['label' => 'Sổ phác thảo', 'desc' => 'Giấy kẻ ô, nét bút dạ, ảnh dán băng keo — hợp dân minh hoạ.'],
        'editorial' => ['label' => 'Tạp chí', 'desc' => 'Măng-sét như bìa tạp chí, chữ có chân thật lớn, chú thích ảnh in nghiêng — sang và chững chạc.'],
        'swatch' => ['label' => 'Thẻ màu', 'desc' => 'Tên studio in trên thẻ màu như quạt màu Pantone, kèm mã màu và dải sắc độ — hợp dân nhận diện thương hiệu.'],
    ],

    /*
     * Mẫu brief có sẵn để gắn vào dịch vụ (Dịch vụ & bảng giá → Mẫu brief → Dùng mẫu). Chỉ là điểm xuất phát:
     * chép vào dịch vụ rồi sửa tuỳ ý. Kiểu câu hỏi: short (1 dòng), long (đoạn văn), choice (chọn 1), multi (chọn nhiều).
     */
    'brief' => [
        'max_questions' => 12,
        'max_options' => 12,
        'types' => ['short' => 'Trả lời ngắn', 'long' => 'Đoạn văn', 'choice' => 'Chọn 1', 'multi' => 'Chọn nhiều'],
        'presets' => [
            'logo' => ['label' => 'Logo', 'questions' => [
                ['label' => 'Tên thương hiệu trên logo (đúng từng chữ, có dấu)', 'type' => 'short', 'required' => true],
                ['label' => 'Slogan / dòng phụ đi kèm (nếu có)', 'type' => 'short'],
                ['label' => 'Bạn kinh doanh gì, khách hàng chính là ai?', 'type' => 'long', 'required' => true],
                ['label' => 'Logo dùng nhiều nhất ở đâu?', 'type' => 'multi', 'options' => ['Biển hiệu', 'Bao bì, tem nhãn', 'Mạng xã hội', 'Website, ứng dụng', 'Đồng phục', 'Giấy tờ văn phòng']],
                ['label' => 'Kiểu logo bạn nghiêng về', 'type' => 'choice', 'options' => ['Chỉ chữ (wordmark)', 'Biểu tượng + chữ', 'Chữ cái viết tắt (monogram)', 'Huy hiệu (emblem)', 'Chưa rõ, nhờ tư vấn']],
                ['label' => 'Cảm giác muốn khách nhận được', 'type' => 'multi', 'options' => ['Sang trọng', 'Thân thiện', 'Trẻ trung', 'Truyền thống', 'Tối giản', 'Mạnh mẽ', 'Vui nhộn']],
                ['label' => 'Logo bạn thích hoặc đối thủ cần khác biệt', 'type' => 'long', 'hint' => 'Kể tên hoặc dán link.'],
                ['label' => 'Điều nhất định tránh (màu, hình, kiểu chữ…)', 'type' => 'short'],
            ]],
            'packaging' => ['label' => 'Bao bì, tem nhãn', 'questions' => [
                ['label' => 'Tên sản phẩm và dòng sản phẩm', 'type' => 'short', 'required' => true],
                ['label' => 'Loại bao bì', 'type' => 'choice', 'required' => true, 'options' => ['Hộp giấy', 'Túi', 'Nhãn dán chai, lọ', 'Túi zip', 'Hũ, lon', 'Khác']],
                ['label' => 'Kích thước, quy cách in (nếu xưởng in đã gửi)', 'type' => 'short'],
                ['label' => 'Thông tin bắt buộc in trên bao bì', 'type' => 'multi', 'options' => ['Thành phần', 'NSX / HSD', 'Mã vạch', 'Hướng dẫn sử dụng', 'Số công bố', 'Logo chứng nhận']],
                ['label' => 'Sản phẩm bán ở đâu?', 'type' => 'multi', 'options' => ['Cửa hàng, siêu thị', 'Sàn online (Shopee, TikTok…)', 'Làm quà tặng', 'Xuất khẩu']],
                ['label' => 'Giá bán dự kiến mỗi sản phẩm', 'type' => 'short'],
                ['label' => 'Đã có xưởng in chưa?', 'type' => 'choice', 'options' => ['Đã có', 'Chưa, nhờ giới thiệu']],
            ]],
            'social' => ['label' => 'Ảnh mạng xã hội', 'questions' => [
                ['label' => 'Đăng ở đâu?', 'type' => 'multi', 'required' => true, 'options' => ['Facebook', 'Instagram', 'TikTok', 'Zalo OA', 'Shopee']],
                ['label' => 'Số lượng ảnh / bài cần làm', 'type' => 'short', 'required' => true],
                ['label' => 'Kích thước', 'type' => 'multi', 'options' => ['Vuông 1:1', 'Dọc 4:5', 'Story 9:16', 'Ảnh bìa']],
                ['label' => 'Nội dung, chương trình cần truyền tải', 'type' => 'long', 'required' => true],
                ['label' => 'Đã có ảnh sản phẩm chưa?', 'type' => 'choice', 'options' => ['Có, ảnh đẹp sẵn', 'Có nhưng cần chỉnh', 'Chưa có']],
            ]],
            'identity' => ['label' => 'Bộ nhận diện', 'questions' => [
                ['label' => 'Tên thương hiệu', 'type' => 'short', 'required' => true],
                ['label' => 'Đã có logo chưa?', 'type' => 'choice', 'required' => true, 'options' => ['Chưa, làm từ đầu', 'Có, giữ nguyên', 'Có, muốn làm mới']],
                ['label' => 'Hạng mục cần làm', 'type' => 'multi', 'options' => ['Logo', 'Bảng màu & font', 'Danh thiếp', 'Bao thư, giấy tiêu đề', 'Mẫu mạng xã hội', 'Đồng phục', 'Biển hiệu', 'Sổ tay hướng dẫn']],
                ['label' => 'Câu chuyện, giá trị cốt lõi của thương hiệu', 'type' => 'long'],
                ['label' => 'Khách hàng mục tiêu', 'type' => 'long', 'required' => true],
            ]],
            'illustration' => ['label' => 'Minh hoạ', 'questions' => [
                ['label' => 'Minh hoạ dùng để làm gì?', 'type' => 'choice', 'required' => true, 'options' => ['Sách, truyện', 'Bao bì', 'Áo, đồ lưu niệm', 'Mạng xã hội', 'Website, ứng dụng', 'Khác']],
                ['label' => 'Số lượng hình và kích thước', 'type' => 'short', 'required' => true],
                ['label' => 'Phong cách vẽ mong muốn', 'type' => 'long', 'hint' => 'Dán link vài tranh bạn thích.'],
                ['label' => 'Màu hay đen trắng?', 'type' => 'choice', 'options' => ['Màu', 'Đen trắng', 'Chưa rõ']],
                ['label' => 'Cần quyền dùng thương mại (in bán, quảng cáo)?', 'type' => 'choice', 'options' => ['Có', 'Không', 'Chưa rõ']],
            ]],
            'print' => ['label' => 'Ấn phẩm in', 'questions' => [
                ['label' => 'Loại ấn phẩm', 'type' => 'choice', 'required' => true, 'options' => ['Poster', 'Menu', 'Catalogue, brochure', 'Tờ rơi', 'Standee', 'Khác']],
                ['label' => 'Kích thước và số trang', 'type' => 'short', 'required' => true],
                ['label' => 'Nội dung đã có sẵn chưa?', 'type' => 'choice', 'options' => ['Có đủ chữ và ảnh', 'Có một phần', 'Chưa có']],
                ['label' => 'Ngày cần in / ngày sự kiện', 'type' => 'short'],
                ['label' => 'In ở đâu?', 'type' => 'choice', 'options' => ['Đã có nhà in', 'Nhờ giới thiệu', 'In kỹ thuật số số lượng ít']],
            ]],
        ],
    ],

    // "Tác phẩm tiêu biểu" trên trang đặt đơn.
    'portfolio' => [
        'max_items' => 9,
        'max_px' => 1600,
        'cover_max_px' => 2000,   // ảnh bìa trang đặt đơn
    ],

    // Slug không được dùng làm địa chỉ trang đặt đơn (trùng đường dẫn hệ thống / dễ gây nhầm).
    'reserved_slugs' => ['admin', 'dat', 'theo-doi', 'login', 'dang-ky', 'api', 'net', 'storage', 'build', 'install', 'sieu-quan-tri', 'ho-so', 'cai-dat', 'www'],
];
