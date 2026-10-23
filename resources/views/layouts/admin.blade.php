<!DOCTYPE html>
<html lang="vi" class="h-full" data-accent="ink">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị · Nét')</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[var(--paper)] text-[var(--ink)] antialiased">
    @php
        $adminNav = [
            ['route' => 'admin.dashboard', 'active' => ['admin.dashboard', 'admin.studios.*'], 'icon' => 'fa-store', 'label' => 'Studio'],
            ['route' => 'admin.users.index', 'active' => ['admin.users.*'], 'icon' => 'fa-users', 'label' => 'Tài khoản'],
            ['route' => 'admin.settings.edit', 'active' => ['admin.settings.*'], 'icon' => 'fa-gear', 'label' => 'Cài đặt hệ thống'],
        ];
        $adminPages = [
            'gioi-thieu' => 'Giới thiệu',
            'dieu-khoan-su-dung' => 'Điều khoản sử dụng',
            'chinh-sach-bao-mat' => 'Chính sách bảo mật',
        ];
        $linkClass = fn ($on) => 'flex items-center gap-3 px-3 py-2 rounded-md text-sm transition '.($on ? 'bg-[var(--ink)] text-white font-semibold' : 'text-[var(--ink-2)] hover:bg-stone-100');
    @endphp
    <div class="flex h-full" x-data="{ menu: false }">
        <aside class="fixed md:static inset-y-0 left-0 z-30 w-60 shrink-0 bg-white border-r border-[var(--line)] h-full flex flex-col transition-transform md:translate-x-0"
               :class="menu ? 'translate-x-0' : '-translate-x-full'">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-5 py-4 border-b border-[var(--line)]">
                <img src="/favicon.svg" alt="" class="w-8 h-8">
                <span class="min-w-0"><span class="block font-display font-extrabold text-lg leading-tight">Nét</span><span class="block text-xs text-[var(--muted)] leading-tight">Quản trị hệ thống</span></span>
            </a>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto" aria-label="Quản trị">
                @foreach($adminNav as $item)
                    <a href="{{ route($item['route']) }}" class="{{ $linkClass(request()->routeIs(...$item['active'])) }}"><i class="fa-solid {{ $item['icon'] }} w-4 text-center" aria-hidden="true"></i>{{ $item['label'] }}</a>
                @endforeach
                <p class="px-3 pt-5 pb-1 text-xs font-semibold text-[var(--muted)] uppercase tracking-wider">Trang nội dung</p>
                @foreach($adminPages as $slug => $label)
                    <a href="{{ route('admin.pages.edit', $slug) }}" class="{{ $linkClass(request()->routeIs('admin.pages.*') && request()->route('slug') === $slug) }}"><i class="fa-regular fa-file-lines w-4 text-center" aria-hidden="true"></i>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="px-3 py-3 border-t border-[var(--line)]">
                <a href="{{ route('today') }}" class="{{ $linkClass(false) }}"><i class="fa-solid fa-house w-4 text-center" aria-hidden="true"></i>Vào khu làm việc</a>
                <a href="{{ route('profile.edit') }}" class="{{ $linkClass(request()->routeIs('profile.*')) }}"><i class="fa-solid fa-user w-4 text-center" aria-hidden="true"></i>Hồ sơ cá nhân</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm text-red-600 hover:bg-red-50 text-left"><i class="fa-solid fa-right-from-bracket w-4 text-center" aria-hidden="true"></i>Đăng xuất</button>
                </form>
            </div>
        </aside>
        <div class="fixed inset-0 z-20 bg-black/30 md:hidden" x-show="menu" x-cloak @click="menu = false"></div>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="md:hidden flex items-center gap-3 px-4 py-3 bg-white border-b border-[var(--line)]">
                <button type="button" class="w-9 h-9 rounded-md hover:bg-stone-100" @click="menu = true" aria-label="Mở menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
                <span class="font-display font-extrabold">Nét</span><span class="text-sm text-[var(--muted)]">Quản trị</span>
            </header>
            <main class="relative flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 md:p-10">
                <div class="max-w-5xl mx-auto">
                    @if(session('status'))
                        <div class="mb-5 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
                    @endif
                    <x-errors class="mb-5" />
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
