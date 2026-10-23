<!DOCTYPE html>
{{--
    Trang khách xem (đặt đơn, theo dõi): mang tên, màu và MẪU GIAO DIỆN của studio; Nét chỉ ký nhỏ ở chân trang.
    Biến: $studio, $theme (tuỳ chọn — mặc định mẫu studio đã chọn; trang đặt đơn truyền mẫu "xem thử").
    Mẫu = [data-theme] trên <html> + CSS trong resources/css/themes.css.
--}}
@php $theme ??= $studio->pageTheme(); @endphp
<html lang="vi" class="h-full" data-accent="{{ $studio->accent_color ?? 'magenta' }}" data-theme="{{ $theme }}" style="{{ $studio->accentStyle() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', $studio->name)</title>
    {{--
        Thẻ chia sẻ (Facebook, Messenger, Zalo, Threads, X, iMessage…). Trang con đặt: title, share_title (tuỳ chọn),
        meta_description, canonical (tuỳ chọn). Ảnh luôn là ảnh chia sẻ 1200×630 JPEG do Nét tạo (App\Services\ShareImage).
    --}}
    @php
        $shareImage = $studio->shareImageUrl();
        $shareImageAlt = $studio->name.' — '.app(\App\Services\ShareImage::class)->headline($studio);
        // yieldContent() trả về chuỗi ĐÃ escape (giống @yield) → in bằng {!! !!}, không escape lần 2.
        $shareTitle = $__env->hasSection('share_title') ? $__env->yieldContent('share_title') : $__env->yieldContent('title', $studio->name);
        $shareDescription = $__env->hasSection('meta_description') ? $__env->yieldContent('meta_description') : e($studio->shareDescription());
        $shareUrl = $__env->hasSection('canonical') ? $__env->yieldContent('canonical') : e(url()->current());
    @endphp
    <meta name="description" content="{!! $shareDescription !!}">
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')">
    @hasSection('canonical')<link rel="canonical" href="@yield('canonical')">@endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $studio->name }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{!! $shareTitle !!}">
    <meta property="og:description" content="{!! $shareDescription !!}">
    <meta property="og:url" content="{!! $shareUrl !!}">
    <meta property="og:image" content="{{ $shareImage }}">
    @if(str_starts_with($shareImage, 'https://'))<meta property="og:image:secure_url" content="{{ $shareImage }}">@endif
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="{{ \App\Services\ShareImage::WIDTH }}">
    <meta property="og:image:height" content="{{ \App\Services\ShareImage::HEIGHT }}">
    <meta property="og:image:alt" content="{{ $shareImageAlt }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{!! $shareTitle !!}">
    <meta name="twitter:description" content="{!! $shareDescription !!}">
    <meta name="twitter:image" content="{{ $shareImage }}">
    <meta name="twitter:image:alt" content="{{ $shareImageAlt }}">
    <meta name="theme-color" content="{{ $studio->accentHex() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pf min-h-full antialiased">
    @if($theme === 'artboard')
        {{-- Thước kẻ như phần mềm thiết kế (chỉ trang trí). --}}
        <div class="pf-ruler pf-ruler-x" aria-hidden="true"></div>
        <div class="pf-ruler pf-ruler-y" aria-hidden="true"></div>
        <div class="pf-ruler-corner" aria-hidden="true"></div>
    @endif

    @hasSection('no_header')
    @else
        <header class="pf-header">
            <div class="max-w-3xl mx-auto px-4 py-3 flex items-center gap-3">
                @if($studio->avatarUrl())
                    <img src="{{ $studio->avatarUrl() }}" alt="" class="pf-avatar w-9 h-9 rounded-full object-cover">
                @else
                    <span class="pf-avatar w-9 h-9 rounded-full bg-[var(--accent)] text-[var(--on-accent)] flex items-center justify-center font-display font-bold text-sm" aria-hidden="true">{{ $studio->initials() }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ $studio->order_form_enabled ? route('public.order', $studio->slug) : '#' }}" class="block font-semibold truncate leading-tight">{{ $studio->name }}</a>
                    @if($studio->tagline)<p class="pf-muted text-xs truncate">{{ $studio->tagline }}</p>@endif
                </div>
                @yield('header-actions')
            </div>
        </header>
    @endif

    <main class="pf-main @yield('main_class', 'max-w-3xl') mx-auto px-4 py-6 md:py-10" data-frame="{{ $studio->slug }} / @yield('frame', 'theo-doi-don')">
        @if(session('status'))
            <div class="mb-5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 flex gap-2" role="status">
                <i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i><span>{{ session('status') }}</span>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="pf-footer max-w-3xl mx-auto px-4 pb-10 pt-2 text-center text-xs">
        <p>
            @if($studio->phone)<a href="https://zalo.me/{{ preg_replace('/\D/', '', $studio->phone) }}" target="_blank" rel="noopener">Zalo {{ $studio->phone }}</a>@endif
            @if($studio->email)<span class="mx-1.5" aria-hidden="true">·</span><a href="mailto:{{ $studio->email }}">{{ $studio->email }}</a>@endif
            @if($studio->contact_url)<span class="mx-1.5" aria-hidden="true">·</span><a href="{{ $studio->contact_url }}" target="_blank" rel="noopener">{{ link_host_label($studio->contact_url) }}</a>@endif
        </p>
        <p class="mt-3">Quản lý đơn bằng <a href="{{ route('home') }}" class="font-display font-bold">Nét</a></p>
    </footer>
</body>
</html>
