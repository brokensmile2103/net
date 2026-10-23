<!DOCTYPE html>
<html lang="vi" data-accent="magenta">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seoTitle = 'Nét — Quản lý đơn thiết kế cho freelancer: brief, phương án, góp ý, cọc & bàn giao';
        $seoDescription = 'Nét cho designer làm tự do: khách đặt đơn qua link riêng, bạn gửi phương án V1, V2…, khách ghim góp ý lên hình, duyệt, chuyển cọc bằng VietQR và nhận file gốc qua Google Drive khi đã trả đủ. Không cần cài app.';
        $seoImage = asset('og-image.png');
        $seoUrl = url()->current();
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $seoUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Nét">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <meta name="theme-color" content="#1d1d22">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /*
         * Nét — trang giới thiệu. Ý tưởng: "tờ in thử" (proof sheet) của xưởng in — dấu góc cắt, dấu chồng màu,
         * thang màu CMYK ở mép giấy, ghi chú khoanh đỏ của khách. Thứ mà designer nào cũng từng cầm trên tay,
         * nay là một đường link. Bricolage Grotesque cho tiêu đề, Be Vietnam Pro cho chữ đọc.
         */
        .n-page { --c: #00a3d9; --m: #c8106e; --y: #f2c200; --k: #1d1d22; background: var(--paper); color: var(--ink); }
        .n-wrap { max-width: 72rem; margin: 0 auto; padding-left: 1.25rem; padding-right: 1.25rem; }
        @media (min-width: 768px) { .n-wrap { padding-left: 2rem; padding-right: 2rem; } }

        /* Thang màu ở mép trên */
        .n-colorbar { display: flex; height: 6px; }
        .n-colorbar span { flex: 1; }

        /* Dấu chồng màu (registration mark) */
        .n-reg { width: 22px; height: 22px; position: relative; display: inline-block; flex-shrink: 0; }
        .n-reg::before { content: ''; position: absolute; inset: 3px; border: 1.5px solid currentColor; border-radius: 50%; }
        .n-reg::after { content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 1.5px; margin-left: -.75px; background: currentColor;
            box-shadow: 0 0 0 0 transparent; }
        .n-reg i { position: absolute; top: 50%; left: 0; right: 0; height: 1.5px; margin-top: -.75px; background: currentColor; }

        .n-display { font-family: var(--font-display); font-optical-sizing: auto; letter-spacing: -.035em; line-height: .95; }
        .n-kicker { font-size: .75rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }

        /* Tờ in thử */
        .n-sheet { position: relative; background: #fff; box-shadow: 0 1px 0 var(--line), 0 30px 60px -30px rgba(29,29,34,.35); }
        .n-sheet-crop { position: absolute; width: 18px; height: 18px; border-color: var(--k); }
        .n-sheet-crop.tl { top: -10px; left: -10px; border-top: 1px solid; border-left: 1px solid; }
        .n-sheet-crop.tr { top: -10px; right: -10px; border-top: 1px solid; border-right: 1px solid; }
        .n-sheet-crop.bl { bottom: -10px; left: -10px; border-bottom: 1px solid; border-left: 1px solid; }
        .n-sheet-crop.br { bottom: -10px; right: -10px; border-bottom: 1px solid; border-right: 1px solid; }

        /* Ghim góp ý */
        .n-pin { position: absolute; width: 28px; height: 28px; margin: -14px 0 0 -14px; border-radius: 50%; background: var(--m); color: #fff;
            font: 700 .75rem/28px var(--font-sans); text-align: center; box-shadow: 0 0 0 3px #fff, 0 6px 14px rgba(0,0,0,.25); }
        .n-pin.is-k { background: var(--k); }
        .n-pin::after { content: ''; position: absolute; inset: -6px; border-radius: 50%; border: 2px solid var(--m); opacity: 0; animation: n-ping 2.4s ease-out infinite; }
        @keyframes n-ping { 0% { transform: scale(.6); opacity: .7; } 80%, 100% { transform: scale(1.5); opacity: 0; } }

        .n-note { position: absolute; background: #fff; border-radius: 8px; padding: .55rem .75rem; font-size: .78rem; line-height: 1.35;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,.3); border: 1px solid var(--line); max-width: 13rem; }

        /* Bảng thao tác V1 V2 */
        .n-tab { font-family: var(--font-display); font-weight: 800; letter-spacing: -.02em; }

        /* Quy trình */
        .n-flow { counter-reset: step; }
        .n-flow li { counter-increment: step; }
        .n-flow li .n-num::before { content: counter(step, decimal-leading-zero); }

        .n-stroke { -webkit-text-stroke: 1.5px var(--ink); color: transparent; }

        .n-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; }
        .n-dash { background-image: linear-gradient(90deg, var(--line-strong) 50%, transparent 50%); background-size: 8px 1px; background-repeat: repeat-x; height: 1px; }

        .n-faq summary::-webkit-details-marker { display: none; }
        .n-faq[open] .n-faq-icon { transform: rotate(45deg); }

        @media (prefers-reduced-motion: reduce) { .n-pin::after { animation: none; } }
    </style>
</head>
<body class="antialiased">
<div class="n-page">
    <div class="n-colorbar" aria-hidden="true"><span style="background:var(--c)"></span><span style="background:var(--m)"></span><span style="background:var(--y)"></span><span style="background:var(--k)"></span></div>

    {{-- Thanh điều hướng --}}
    <header class="n-wrap flex items-center gap-4 py-5">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="Nét — trang chủ">
            <img src="/favicon.svg" alt="" class="w-9 h-9">
            <span class="n-display text-2xl font-extrabold">Nét</span>
        </a>
        <nav class="hidden md:flex items-center gap-6 ml-8 text-sm text-[var(--ink-2)]" aria-label="Trang chủ">
            <a href="#quy-trinh" class="hover:text-[var(--ink)]">Quy trình</a>
            <a href="#tinh-nang" class="hover:text-[var(--ink)]">Tính năng</a>
            <a href="#khach-thay-gi" class="hover:text-[var(--ink)]">Khách thấy gì</a>
            <a href="#hoi-dap" class="hover:text-[var(--ink)]">Hỏi đáp</a>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('login') }}" class="text-sm font-semibold px-3 py-2 rounded-md hover:bg-white">Đăng nhập</a>
            @if(\App\Models\PlatformSetting::registrationOpen())
                <a href="{{ route('register') }}" class="text-sm font-semibold px-4 py-2 rounded-md bg-[var(--ink)] text-white hover:bg-black">Mở studio</a>
            @endif
        </div>
    </header>

    <main>
        {{-- HERO --}}
        <section class="n-wrap pt-8 md:pt-14 pb-20 md:pb-28 grid lg:grid-cols-[1.05fr_1fr] gap-14 lg:gap-10 items-center">
            <div>
                <p class="n-kicker flex items-center gap-2.5"><span class="n-reg text-[var(--ink)]" aria-hidden="true"><i></i></span>Cho designer làm tự do</p>
                <h1 class="n-display font-extrabold text-[2.9rem] sm:text-[4rem] lg:text-[4.4rem] mt-5">
                    <span class="whitespace-nowrap">Từ brief</span> <span class="whitespace-nowrap">tới file gốc,</span><br><span class="text-[var(--accent)] whitespace-nowrap">một đường link</span><br>là đủ.
                </h1>
                <p class="mt-7 text-lg text-[var(--ink-2)] max-w-xl leading-relaxed">
                    Thôi lục tin nhắn Zalo tìm “bản cuối cùng thật sự”. Nét gom đơn, phương án, góp ý của khách, tiền cọc và link Drive bàn giao về đúng một chỗ — cho bạn, và cho khách.
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-3">
                    @if(\App\Models\PlatformSetting::registrationOpen())
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white font-semibold px-6 py-3.5">
                            Mở studio miễn phí <i class="fa-solid fa-arrow-right text-sm" aria-hidden="true"></i>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white font-semibold px-6 py-3.5">Đăng nhập</a>
                    @endif
                    <a href="#khach-thay-gi" class="inline-flex items-center gap-2 rounded-lg px-5 py-3.5 font-semibold text-[var(--ink)] ring-1 ring-[var(--line-strong)] hover:ring-[var(--ink)] bg-white">Xem trang của khách</a>
                </div>
                <p class="mt-5 text-sm text-[var(--muted)]">Không cần cài app · Khách không cần tài khoản · File gốc vẫn ở Google Drive của bạn</p>
            </div>

            {{-- Tờ in thử: phương án + ghim góp ý --}}
            <div class="relative mx-3 sm:mx-6 lg:mx-0" aria-label="Minh hoạ: khách ghim góp ý lên phương án V2" role="img">
                <div class="n-sheet rounded-sm p-4 sm:p-5">
                    <span class="n-sheet-crop tl"></span><span class="n-sheet-crop tr"></span><span class="n-sheet-crop bl"></span><span class="n-sheet-crop br"></span>
                    <div class="flex items-center gap-2 text-xs text-[var(--muted)]">
                        <span class="n-tab text-[var(--muted)] text-base">V1</span>
                        <span class="n-tab text-[var(--ink)] text-base border-b-2 border-[var(--ink)]">V2</span>
                        <span class="ml-auto font-semibold">N0042 · Logo tiệm bánh Mây</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        {{-- Phương án A --}}
                        <div class="relative aspect-square rounded bg-[#f7efe4] flex flex-col items-center justify-center">
                            <svg viewBox="0 0 120 70" class="w-[62%]" aria-hidden="true">
                                <circle cx="38" cy="42" r="20" fill="#e2875a"/><circle cx="62" cy="30" r="24" fill="#e2875a"/><circle cx="86" cy="42" r="18" fill="#e2875a"/>
                                <rect x="20" y="42" width="84" height="20" rx="10" fill="#e2875a"/>
                                <path d="M44 50 q16 10 32 0" stroke="#f7efe4" stroke-width="4" fill="none" stroke-linecap="round"/>
                            </svg>
                            <p class="n-display font-extrabold text-[#7a3b1d] text-lg sm:text-2xl mt-1">mây</p>
                            <p class="text-[0.5rem] sm:text-[0.6rem] tracking-[.3em] text-[#7a3b1d]/70 font-semibold">TIỆM BÁNH</p>
                            <span class="n-pin" style="left:66%; top:24%">1</span>
                            <span class="absolute left-2 top-2 text-[0.65rem] font-semibold text-[#7a3b1d]/60">A</span>
                        </div>
                        {{-- Phương án B --}}
                        <div class="relative aspect-square rounded bg-[#26314a] flex flex-col items-center justify-center ring-2 ring-emerald-500 ring-offset-2">
                            <svg viewBox="0 0 120 70" class="w-[52%]" aria-hidden="true">
                                <path d="M20 55 a18 18 0 0 1 14-30 a26 26 0 0 1 48-4 a18 18 0 0 1 18 34 z" fill="none" stroke="#f3d9a4" stroke-width="5" stroke-linejoin="round"/>
                            </svg>
                            <p class="n-display font-extrabold text-[#f3d9a4] text-lg sm:text-2xl mt-1 tracking-tight">Mây</p>
                            <p class="text-[0.5rem] sm:text-[0.6rem] tracking-[.3em] text-[#f3d9a4]/70 font-semibold">BAKERY · 2026</p>
                            <span class="n-pin is-k" style="left:72%; top:76%">2</span>
                            <span class="absolute left-2 top-2 text-[0.65rem] font-semibold text-[#f3d9a4]/60">B</span>
                            <span class="absolute -top-2.5 -right-2.5 bg-emerald-500 text-white text-[0.65rem] font-bold rounded-full px-2 py-0.5"><i class="fa-solid fa-check" aria-hidden="true"></i> Khách chọn</span>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <span class="flex-1 n-dash"></span>
                        <span class="text-[0.65rem] text-[var(--muted)] tracking-wider">ĐÃ SỬA 1/2 LẦN</span>
                        <span class="flex-1 n-dash"></span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <span class="flex-1 rounded-md bg-emerald-600 text-white text-xs sm:text-sm font-semibold py-2 text-center"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Duyệt B</span>
                        <span class="flex-1 rounded-md bg-[var(--ink)] text-white text-xs sm:text-sm font-semibold py-2 text-center"><i class="fa-solid fa-rotate mr-1" aria-hidden="true"></i>Gửi góp ý</span>
                    </div>
                </div>

                <div class="n-note hidden sm:block" style="left:1.5rem; top:-2.6rem">
                    <span class="font-bold text-[var(--m)]">① Khách:</span> Đám mây tròn quá, trông như bánh bao 😅
                </div>
                <div class="n-note" style="right:-0.75rem; bottom:-2.6rem">
                    <span class="font-bold">② Khách:</span> Chữ này ok, cho to lên chút nha!
                </div>

                {{-- Thẻ cọc --}}
                <div class="absolute -bottom-10 -left-2 sm:left-6 n-card px-4 py-3 shadow-xl flex items-center gap-3 rotate-[-2deg]">
                    <span class="w-10 h-10 rounded grid grid-cols-3 gap-[2px] p-1 bg-[var(--ink)]" aria-hidden="true">
                        @foreach([1,0,1,0,1,1,1,1,0] as $b)<span class="{{ $b ? 'bg-white' : '' }}"></span>@endforeach
                    </span>
                    <span>
                        <span class="block text-[0.65rem] text-[var(--muted)]">Đã nhận cọc 50%</span>
                        <span class="block n-display font-bold text-lg leading-tight tnum">1.250.000đ</span>
                    </span>
                </div>
            </div>
        </section>

        {{-- Vấn đề --}}
        <section class="bg-[var(--ink)] text-white">
            <div class="n-wrap py-16 md:py-20 grid md:grid-cols-[1fr_1.4fr] gap-10 items-start">
                <h2 class="n-display font-extrabold text-4xl md:text-5xl">Làm đẹp<br>đã đủ mệt.</h2>
                <div class="grid sm:grid-cols-2 gap-x-10 gap-y-7 text-[0.98rem]">
                    @foreach([
                        ['Brief rải rác', 'Một nửa trong Zalo, một nửa trong voice note, ảnh mẫu thì trên Pinterest của ai đó.'],
                        ['“Bản final_v3_sửa_lần_cuối”', 'Khách góp ý trên ảnh chụp màn hình đã nén, khoanh tròn bằng ngón tay.'],
                        ['Sửa không có điểm dừng', 'Thoả thuận 2 lần sửa, rồi lần thứ 6 vẫn là “chỉnh xíu thôi em”.'],
                        ['Giao file rồi mới đòi tiền', 'Gửi link Drive xong, phần còn lại của khoản thanh toán “để tuần sau”.'],
                    ] as [$t, $d])
                        <div>
                            <p class="font-semibold text-white">{{ $t }}</p>
                            <p class="text-white/65 mt-1 leading-relaxed">{{ $d }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Quy trình --}}
        <section id="quy-trinh" class="n-wrap py-20 md:py-28 scroll-mt-6">
            <p class="n-kicker">Quy trình</p>
            <h2 class="n-display font-extrabold text-4xl md:text-6xl mt-3 max-w-3xl">Sáu bước, <span class="n-stroke">một</span> đường link cho khách.</h2>
            <ol class="n-flow mt-14 grid sm:grid-cols-2 lg:grid-cols-3 border-t border-l border-[var(--line-strong)]">
                @foreach([
                    ['Khách đặt đơn', 'Bạn gửi link /dat/ten-cua-ban. Khách chọn dịch vụ trong bảng giá, viết brief, dán link Drive tài liệu.', 'Khách', 'fa-inbox'],
                    ['Bạn báo giá', 'Phí thiết kế, phụ phí, giảm giá, % cọc, số lần sửa. Khách bấm “Đồng ý báo giá” ngay trên trang.', 'Bạn', 'fa-file-signature'],
                    ['Gửi phương án V1', 'Mỗi phương án vài ảnh xem trước đã nén nhẹ, kèm link file lớn trên Drive. Vòng nháp chỉ bạn thấy.', 'Bạn', 'fa-layer-group'],
                    ['Khách ghim góp ý', 'Chạm vào đúng chỗ trên hình để ghim, chọn phương án ưng, rồi duyệt — hoặc gửi góp ý để sửa.', 'Khách', 'fa-location-dot'],
                    ['Thu tiền', 'Mã VietQR đúng số tiền cọc hoặc phần còn lại, nội dung chuyển khoản theo mã đơn.', 'Khách', 'fa-qrcode'],
                    ['Bàn giao', 'Dán link Drive file gốc. Nếu bật khoá, link chỉ hiện khi khách đã thanh toán đủ.', 'Bạn', 'fa-box-archive'],
                ] as [$t, $d, $who, $icon])
                    <li class="relative border-r border-b border-[var(--line-strong)] p-6 md:p-7 bg-[var(--paper)] hover:bg-white transition">
                        <div class="flex items-center gap-3">
                            <span class="n-num n-display font-extrabold text-3xl text-[var(--accent)]"></span>
                            <span class="ml-auto text-[0.7rem] font-semibold rounded-full px-2.5 py-1 {{ $who === 'Khách' ? 'bg-[var(--accent-soft)] text-[var(--accent-ink)]' : 'bg-[var(--ink)] text-white' }}">{{ $who }}</span>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold"><i class="fa-solid {{ $icon }} text-[var(--muted)] mr-2 text-base" aria-hidden="true"></i>{{ $t }}</h3>
                        <p class="mt-2 text-[var(--ink-2)] leading-relaxed text-[0.95rem]">{{ $d }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Tính năng --}}
        <section id="tinh-nang" class="bg-white border-y border-[var(--line)] scroll-mt-6">
            <div class="n-wrap py-20 md:py-28">
                <p class="n-kicker">Phía bạn</p>
                <h2 class="n-display font-extrabold text-4xl md:text-6xl mt-3 max-w-3xl">Một bàn làm việc gọn như artboard.</h2>

                <div class="mt-14 grid lg:grid-cols-3 gap-5">
                    {{-- Bảng việc --}}
                    <article class="lg:col-span-2 rounded-xl border border-[var(--line)] bg-[var(--paper)] p-6 md:p-7 overflow-hidden">
                        <h3 class="text-xl font-semibold">Bảng việc theo giai đoạn</h3>
                        <p class="mt-1.5 text-[var(--ink-2)]">Kéo thẻ từ “Đang thiết kế” sang “Chờ khách xem”. Ảnh bìa trên mỗi thẻ để nhìn là nhớ đơn nào.</p>
                        <div class="mt-6 grid grid-cols-3 gap-3 text-xs" aria-hidden="true">
                            @foreach([
                                ['Đang thiết kế', 'bg-sky-500', [['N0044', 'Bao bì trà sữa Lá', '#cfe8d5', 'Còn 3 ngày'], ['N0045', 'Menu quán phở', '#f3e2c7', 'Còn 6 ngày']]],
                                ['Chờ khách xem', 'bg-[var(--accent)]', [['N0042', 'Logo tiệm bánh Mây', '#26314a', 'Gửi V2 hôm qua']]],
                                ['Đang sửa', 'bg-orange-500', [['N0039', 'Poster hội chợ sách', '#f2c200', 'Sửa 2/2 lần']]],
                            ] as [$col, $dot, $cards])
                                <div class="rounded-lg bg-stone-200/60 p-2">
                                    <p class="font-semibold px-1 py-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>{{ $col }}</p>
                                    @foreach($cards as [$code, $name, $bg, $meta])
                                        <div class="mt-2 rounded-md bg-white ring-1 ring-[var(--line)] overflow-hidden">
                                            <div class="h-10 sm:h-14" style="background: {{ $bg }}"></div>
                                            <div class="p-2">
                                                <p class="text-[0.6rem] text-[var(--muted)] font-semibold">{{ $code }}</p>
                                                <p class="font-semibold leading-tight">{{ $name }}</p>
                                                <p class="text-[0.6rem] text-[var(--muted)] mt-1">{{ $meta }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </article>

                    {{-- Hôm nay --}}
                    <article class="rounded-xl border border-[var(--line)] bg-[var(--paper)] p-6 md:p-7">
                        <h3 class="text-xl font-semibold">Mở máy là biết làm gì</h3>
                        <p class="mt-1.5 text-[var(--ink-2)]">Đơn mới chờ nhận, đơn trễ hạn, khách vừa phản hồi, đơn chờ khách quá 3 ngày.</p>
                        <ul class="mt-6 space-y-2 text-sm" aria-hidden="true">
                            <li class="rounded-md bg-white ring-1 ring-[var(--line)] px-3 py-2.5 flex gap-2.5"><i class="fa-solid fa-hand-pointer text-[var(--accent)] mt-0.5"></i><span><b>Chị Thảo</b> chọn Phương án B</span></li>
                            <li class="rounded-md bg-white ring-1 ring-[var(--line)] px-3 py-2.5 flex gap-2.5"><i class="fa-solid fa-rotate text-orange-500 mt-0.5"></i><span><b>Anh Khoa</b> gửi 4 góp ý trên V1</span></li>
                            <li class="rounded-md bg-white ring-1 ring-red-200 px-3 py-2.5 flex gap-2.5 text-red-700"><i class="fa-solid fa-triangle-exclamation mt-0.5"></i><span>Poster hội chợ sách trễ 1 ngày</span></li>
                        </ul>
                    </article>

                    @foreach([
                        ['fa-tags', 'Bảng giá & dịch vụ', 'Giá cố định hoặc “từ …”, thời gian làm, số lần sửa, % cọc, gợi ý khách cần chuẩn bị gì cho brief.'],
                        ['fa-brands fa-google-drive', 'Drive là kho, Nét là mục lục', 'Tài liệu khách gửi, file lớn của từng phương án, file bàn giao đều là link — không giới hạn dung lượng của bạn.'],
                        ['fa-rotate', 'Đếm lần sửa giúp bạn', 'Nét đếm từng vòng khách yêu cầu sửa, nhắc khi vượt thoả thuận để bạn thêm “Phí sửa thêm” vào báo giá.'],
                        ['fa-lock', 'Khoá file tới khi trả đủ', 'Link file gốc chỉ mở khi khoản còn lại về 0. Không phải nhắc nợ khó xử.'],
                        ['fa-calendar', 'Lịch hạn chót', 'Xem cả tháng hạn bàn giao, biết tuần nào đã kín để nhận đơn cho phải.'],
                        ['fa-chart-simple', 'Báo cáo tháng', 'Tiền thực thu, tỉ lệ bàn giao đúng hạn, số vòng sửa trung bình, dịch vụ nào mang lại nhiều nhất.'],
                    ] as [$icon, $t, $d])
                        <article class="rounded-xl border border-[var(--line)] p-6">
                            <i class="{{ str_starts_with($icon, 'fa-brands') ? $icon : 'fa-solid '.$icon }} text-xl text-[var(--accent)]" aria-hidden="true"></i>
                            <h3 class="mt-4 text-lg font-semibold">{{ $t }}</h3>
                            <p class="mt-1.5 text-[var(--ink-2)] leading-relaxed text-[0.95rem]">{{ $d }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Khách thấy gì --}}
        <section id="khach-thay-gi" class="n-wrap py-20 md:py-28 grid lg:grid-cols-2 gap-14 items-center scroll-mt-6">
            <div class="order-2 lg:order-1 flex justify-center">
                {{-- Điện thoại: trang theo dõi --}}
                <div class="w-[19rem] rounded-[2.2rem] bg-[var(--ink)] p-2.5 shadow-2xl" role="img" aria-label="Minh hoạ trang theo dõi đơn trên điện thoại của khách">
                    <div class="rounded-[1.8rem] bg-[var(--paper)] overflow-hidden">
                        <div class="bg-white px-4 py-3 flex items-center gap-2.5 border-b border-[var(--line)]">
                            <span class="w-7 h-7 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.6rem] font-bold flex items-center justify-center">LS</span>
                            <span class="text-xs font-semibold">Linh Studio</span>
                        </div>
                        <div class="p-4">
                            <p class="text-[0.65rem] text-[var(--muted)]">Đơn N0042</p>
                            <p class="n-display font-bold text-xl leading-tight">Logo tiệm bánh Mây</p>
                            <div class="mt-3 grid grid-cols-5 gap-1">
                                @foreach(['Gửi', 'Báo giá', 'Thiết kế', 'Duyệt', 'Giao'] as $i => $s)
                                    <div><span class="block h-1 rounded-full {{ $i <= 2 ? 'bg-[var(--accent)]' : 'bg-[var(--line-strong)]' }}"></span><span class="block text-[0.55rem] mt-1 {{ $i === 2 ? 'font-bold' : 'text-[var(--muted)]' }}">{{ $s }}</span></div>
                                @endforeach
                            </div>
                            <div class="mt-4 rounded-lg bg-white ring-1 ring-[var(--line)] p-3">
                                <p class="text-xs font-semibold">Phương án V2 <span class="ml-1 text-[0.6rem] rounded-full bg-[var(--accent-soft)] text-[var(--accent-ink)] px-1.5 py-0.5">Chờ bạn phản hồi</span></p>
                                <div class="mt-2 grid grid-cols-2 gap-1.5">
                                    <div class="aspect-square rounded bg-[#f7efe4]"></div>
                                    <div class="aspect-square rounded bg-[#26314a] relative"><span class="absolute left-[60%] top-[65%] w-4 h-4 -ml-2 -mt-2 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.5rem] font-bold flex items-center justify-center ring-2 ring-white">1</span></div>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-1.5 text-[0.6rem] font-semibold text-center text-white">
                                    <span class="rounded bg-emerald-600 py-1.5">Duyệt</span><span class="rounded bg-[var(--ink)] py-1.5">Góp ý</span>
                                </div>
                            </div>
                            <div class="mt-3 rounded-lg bg-white ring-1 ring-[var(--line)] p-3 flex items-center gap-3">
                                <span class="w-12 h-12 rounded bg-[var(--ink)] grid grid-cols-4 gap-[2px] p-1">@foreach([1,0,1,1,0,1,0,1,1,1,0,0,1,0,1,1] as $b)<span class="{{ $b ? 'bg-white' : '' }}"></span>@endforeach</span>
                                <span class="text-[0.65rem] leading-tight"><span class="block text-[var(--muted)]">Còn lại</span><span class="block font-bold text-sm">1.250.000đ</span><span class="block text-[var(--muted)]">ND: NET N0042</span></span>
                            </div>
                            <div class="mt-3 rounded-lg bg-white ring-1 ring-[var(--line)] p-3 text-[0.65rem] flex items-center gap-2 text-[var(--muted)]">
                                <i class="fa-solid fa-lock"></i>File gốc mở khi thanh toán đủ
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <p class="n-kicker">Phía khách</p>
                <h2 class="n-display font-extrabold text-4xl md:text-6xl mt-3">Khách mở link, <br>không phải cài gì.</h2>
                <p class="mt-6 text-lg text-[var(--ink-2)] leading-relaxed">Mỗi đơn có một trang theo dõi riêng, mang tên và màu của studio bạn. Khách xem tiến độ, đọc báo giá, chọn phương án, ghim góp ý lên đúng chỗ trên hình, chuyển khoản bằng mã QR và tải file khi xong.</p>
                <ul class="mt-7 space-y-3">
                    @foreach([
                        'Link bí mật 40 ký tự — không ai đoán được, bạn đổi link mới bất cứ lúc nào.',
                        'Ghi chú nội bộ và vòng nháp chưa gửi — khách không bao giờ thấy.',
                        'Tin nhắn mẫu theo từng giai đoạn để dán vào Zalo trong một chạm.',
                    ] as $li)
                        <li class="flex gap-3"><i class="fa-solid fa-check text-[var(--accent)] mt-1.5" aria-hidden="true"></i><span class="text-[var(--ink-2)]">{{ $li }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Hỏi đáp --}}
        <section id="hoi-dap" class="bg-white border-t border-[var(--line)] scroll-mt-6">
            <div class="n-wrap py-20 md:py-24 grid md:grid-cols-[1fr_1.6fr] gap-10">
                <div>
                    <p class="n-kicker">Hỏi đáp</p>
                    <h2 class="n-display font-extrabold text-4xl md:text-5xl mt-3">Hỏi trước<br>cho chắc.</h2>
                </div>
                <div class="divide-y divide-[var(--line)] border-y border-[var(--line)]">
                    @foreach([
                        ['Nét có lưu file thiết kế gốc của tôi không?', 'Không. File gốc (AI, PSD, Figma…) nằm ở Google Drive, Dropbox hay nơi bạn quen dùng — Nét chỉ giữ link. Ảnh xem trước bạn tải lên được tự thu nhỏ và nén nhẹ để khách mở nhanh trên điện thoại.'],
                        ['Khách có cần tạo tài khoản không?', 'Không. Khách đặt đơn qua trang của bạn, sau đó mọi thứ diễn ra trên một link theo dõi riêng của đơn đó.'],
                        ['Tôi tự nhận đơn qua Zalo thì sao?', 'Tạo đơn ngay trong Nét — chọn khách cũ hoặc thêm khách mới — rồi gửi link theo dõi qua Zalo. Khách tự đặt qua trang của bạn thì được nhận ra theo số điện thoại.'],
                        ['Nét có tự thu tiền hộ tôi không?', 'Không. Tiền đi thẳng vào tài khoản ngân hàng của bạn qua mã VietQR. Bạn bấm ghi nhận khi đã nhận được, Nét tự tính còn lại và mở khoá file khi đủ.'],
                        ['Tôi làm cùng một nhóm được không?', 'Hiện Nét dành cho người làm một mình: mỗi tài khoản một studio. Bạn vẫn có thể tạo đơn cho bất kỳ khách nào.'],
                        ['Muốn nghỉ dùng thì dữ liệu đi đâu?', 'Tải toàn bộ dữ liệu (JSON) và danh sách đơn (CSV) bất cứ lúc nào ở Cài đặt. Xoá tài khoản là xoá vĩnh viễn.'],
                    ] as [$q, $a])
                        <details class="n-faq group py-5">
                            <summary class="cursor-pointer list-none flex items-start gap-4">
                                <span class="flex-1 font-semibold text-lg">{{ $q }}</span>
                                <i class="n-faq-icon fa-solid fa-plus mt-1.5 text-[var(--muted)] transition" aria-hidden="true"></i>
                            </summary>
                            <p class="mt-3 text-[var(--ink-2)] leading-relaxed pr-8">{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section class="n-wrap py-20 md:py-28 text-center">
            <span class="n-reg text-[var(--ink)] mx-auto" aria-hidden="true"><i></i></span>
            <h2 class="n-display font-extrabold text-5xl md:text-7xl mt-6">Bản cuối là<br><span class="text-[var(--accent)]">bản được duyệt.</span></h2>
            <p class="mt-6 text-lg text-[var(--ink-2)]">Mở studio trong một phút. Gửi link đặt đơn đầu tiên ngay hôm nay.</p>
            @if(\App\Models\PlatformSetting::registrationOpen())
                <a href="{{ route('register') }}" class="mt-9 inline-flex items-center gap-2 rounded-lg bg-[var(--ink)] hover:bg-black text-white font-semibold px-7 py-4 text-lg">
                    Mở studio miễn phí <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            @else
                <a href="{{ route('login') }}" class="mt-9 inline-flex items-center gap-2 rounded-lg bg-[var(--ink)] hover:bg-black text-white font-semibold px-7 py-4 text-lg">Đăng nhập</a>
            @endif
        </section>
    </main>

    <footer class="border-t border-[var(--line-strong)]">
        <div class="n-wrap py-8 flex flex-col sm:flex-row items-center gap-4 text-sm text-[var(--muted)]">
            <span class="flex items-center gap-2"><img src="/favicon.svg" alt="" class="w-6 h-6"><span class="n-display font-extrabold text-[var(--ink)] text-lg">Nét</span> · Quản lý đơn thiết kế</span>
            <nav class="sm:ml-auto flex flex-wrap justify-center gap-x-5 gap-y-1" aria-label="Chân trang">
                <a href="{{ route('pages.about') }}" class="hover:text-[var(--ink)]">Giới thiệu</a>
                <a href="{{ route('pages.terms') }}" class="hover:text-[var(--ink)]">Điều khoản</a>
                <a href="{{ route('pages.privacy') }}" class="hover:text-[var(--ink)]">Bảo mật</a>
                <a href="{{ route('login') }}" class="hover:text-[var(--ink)]">Đăng nhập</a>
            </nav>
        </div>
        <div class="n-colorbar" aria-hidden="true"><span style="background:var(--c)"></span><span style="background:var(--m)"></span><span style="background:var(--y)"></span><span style="background:var(--k)"></span></div>
    </footer>
</div>
</body>
</html>
