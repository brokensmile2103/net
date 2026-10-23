<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: 'Nét · Quản lý đơn thiết kế cho freelancer';
        $seoDescription = trim($__env->yieldContent('meta_description')) ?: 'Nét — nhận đơn qua link riêng, theo dõi tiến độ, gửi phương án, khách góp ý và duyệt ngay trên trình duyệt, đặt cọc, bàn giao file qua Google Drive.';
        $seoRobots = trim($__env->yieldContent('robots')) ?: 'index, follow';
        $seoUrl = url()->current();
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Nét">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#1d1d22">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[var(--paper)] text-[var(--ink)] antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="flex items-center justify-center gap-2.5 mb-7" aria-label="Nét — trang chủ">
                <img src="/favicon.svg" alt="" class="w-10 h-10">
                <span class="font-display text-3xl font-extrabold tracking-tight">Nét</span>
            </a>
            @hasSection('subtitle')
                <p class="text-center text-[var(--muted)] text-sm -mt-4 mb-6">@yield('subtitle')</p>
            @endif

            @php
                $installSteps = ['install.welcome' => 0, 'install.requirements' => 1, 'install.database' => 2, 'install.account' => 3];
                $currentStep = null;
                foreach ($installSteps as $routeName => $num) {
                    if (request()->routeIs($routeName)) { $currentStep = $num; break; }
                }
            @endphp
            @if(! is_null($currentStep) && $currentStep > 0)
                <div class="flex items-center justify-center gap-2 mb-6" aria-label="Bước {{ $currentStep }}/3">
                    @for ($i = 1; $i <= 3; $i++)
                        <div class="h-1.5 rounded-full {{ $i <= $currentStep ? 'w-8 bg-[var(--ink)]' : 'w-4 bg-[var(--line-strong)]' }}"></div>
                    @endfor
                </div>
            @endif

            @if(session('status'))
                <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</div>
            @endif

            <div class="crop">
                <div class="bg-white rounded-xl border border-[var(--line)] p-6 sm:p-8">
                    @yield('content')
                </div>
            </div>

            <p class="text-center text-xs text-[var(--muted)] mt-7">
                <a href="{{ route('pages.about') }}" class="hover:text-[var(--ink)]">Giới thiệu</a>
                <span aria-hidden="true" class="mx-1.5">/</span>
                <a href="{{ route('pages.terms') }}" class="hover:text-[var(--ink)]">Điều khoản</a>
                <span aria-hidden="true" class="mx-1.5">/</span>
                <a href="{{ route('pages.privacy') }}" class="hover:text-[var(--ink)]">Chính sách bảo mật</a>
            </p>
        </div>
    </div>
</body>
</html>
