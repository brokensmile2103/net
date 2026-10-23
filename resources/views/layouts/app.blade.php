<!DOCTYPE html>
@php
    $studio = $currentStudio ?? null;
    $nav = [
        ['route' => 'today', 'active' => 'today', 'icon' => 'fa-sun', 'label' => 'Hôm nay', 'badge' => $overdueCount ?? 0, 'badge_tone' => 'red'],
        ['route' => 'orders.board', 'active' => 'orders.board', 'icon' => 'fa-table-columns', 'label' => 'Bảng việc', 'badge' => $newOrdersCount ?? 0, 'badge_tone' => 'accent'],
        ['route' => 'orders.index', 'active' => ['orders.index', 'orders.show', 'orders.edit', 'orders.create'], 'icon' => 'fa-list', 'label' => 'Tất cả đơn'],
        ['route' => 'orders.calendar', 'active' => 'orders.calendar', 'icon' => 'fa-calendar', 'label' => 'Lịch hạn chót'],
        ['route' => 'clients.index', 'active' => 'clients.*', 'icon' => 'fa-address-book', 'label' => 'Khách hàng'],
        ['route' => 'services.index', 'active' => 'services.*', 'icon' => 'fa-tags', 'label' => 'Dịch vụ & bảng giá'],
        ['route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'fa-chart-simple', 'label' => 'Báo cáo'],
    ];
    // Hẹn tư vấn: chỉ hiện khi studio bật hoặc đã có lịch hẹn (thêm ở cuối để không đổi vị trí thanh dưới trên điện thoại).
    if ($studio && ($studio->consultEnabled() || $studio->consultations()->exists())) {
        $nav[] = ['route' => 'consultations.index', 'active' => 'consultations.*', 'icon' => 'fa-calendar-check', 'label' => 'Hẹn tư vấn',
            'badge' => $studio->consultations()->where('status', 'booked')->whereBetween('starts_at', [now()->subHour(), now()->endOfDay()])->count(), 'badge_tone' => 'accent', 'badge_title' => 'Hẹn gọi hôm nay'];
    }
    $isActive = fn ($item) => request()->routeIs(...(array) $item['active']);
@endphp
<html lang="vi" class="h-full" data-accent="{{ $studio->accent_color ?? 'magenta' }}" @if($studio) style="{{ $studio->accentStyle() }}" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Nét')</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1d1d22">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Nét">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $shortcutRoutes = auth()->user() && $studio ? [
        'new' => route('orders.create'), 'today' => route('today'), 'board' => route('orders.board'), 'orders' => route('orders.index'),
        'calendar' => route('orders.calendar'), 'clients' => route('clients.index'), 'services' => route('services.index'),
        'reports' => route('reports.index'), 'settings' => route('settings.index'),
    ] : [];
@endphp
<body class="h-full bg-[var(--paper)] text-[var(--ink)] antialiased" @if($shortcutRoutes) data-shortcuts='@json($shortcutRoutes)' @endif>
<div class="md:flex md:h-[100dvh]">

    {{-- Sidebar (từ tablet trở lên) --}}
    <aside class="hidden md:flex md:flex-col w-60 shrink-0 bg-white border-r border-[var(--line)]">
        <a href="{{ route('today') }}" class="h-16 px-5 flex items-center gap-2.5 border-b border-[var(--line)] shrink-0" aria-label="Nét — Hôm nay">
            <img src="/favicon.svg" alt="" class="w-8 h-8">
            <span class="font-display text-2xl font-extrabold tracking-tight">Nét</span>
        </a>

        <div class="px-3 pt-4">
            <a href="{{ route('orders.create') }}" class="flex items-center justify-center gap-2 rounded-lg bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-[var(--on-accent)] text-sm font-semibold py-2.5 transition">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>Đơn mới<kbd class="kbd-hint kbd-on-accent ml-1" data-kbd-alt>Alt N</kbd>
            </a>
            <button type="button" class="mt-2 w-full flex items-center gap-2 rounded-lg border border-[var(--line)] px-3 py-2 text-sm text-[var(--muted)] hover:border-[var(--line-strong)] hover:text-[var(--ink)]" @click="$dispatch('net-cmdk')" x-data>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="flex-1 text-left">Tìm nhanh…</span><kbd class="kbd-hint" data-kbd-mod>Ctrl K</kbd>
            </button>
        </div>

        <nav class="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-0.5" aria-label="Điều hướng chính">
            @foreach($nav as $item)
                @php $on = $isActive($item); @endphp
                <a href="{{ route($item['route']) }}" @if($on) aria-current="page" @endif
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm transition {{ $on ? 'bg-stone-100 text-[var(--ink)] font-semibold' : 'text-[var(--ink-2)] hover:bg-stone-50' }}">
                    <i class="fa-solid {{ $item['icon'] }} w-4 text-center {{ $on ? 'text-[var(--accent)]' : 'text-[var(--muted)]' }}" aria-hidden="true"></i>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if(($item['badge'] ?? 0) > 0)
                        <span class="min-w-5 h-5 px-1.5 rounded-full text-[0.7rem] font-bold flex items-center justify-center {{ $item['badge_tone'] === 'red' ? 'bg-red-100 text-red-700' : 'bg-[var(--accent-soft)] text-[var(--accent-ink)]' }}"
                              title="{{ $item['badge_title'] ?? ($item['badge_tone'] === 'red' ? 'Đơn trễ hạn' : 'Đơn mới chờ nhận') }}">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        @if($studio)
            <div class="mx-3 mb-3 rounded-lg border border-dashed border-[var(--line-strong)] p-3" x-data="{ copied: false }">
                <p class="text-xs text-[var(--muted)] mb-1">Link đặt đơn của bạn</p>
                <p class="text-xs font-medium text-[var(--ink)] truncate" title="{{ $studio->orderFormUrl() }}">{{ preg_replace('#^https?://#', '', $studio->orderFormUrl()) }}</p>
                <div class="flex gap-2 mt-2">
                    <button type="button" class="text-xs font-semibold text-[var(--accent-ink)] hover:underline" @click="copyText(@js($studio->orderFormUrl())).then(() => { copied = true; setTimeout(() => copied = false, 1500) })" x-text="copied ? 'Đã chép' : 'Sao chép'">Sao chép</button>
                    <a href="{{ $studio->orderFormUrl() }}" target="_blank" rel="noopener" class="text-xs text-[var(--muted)] hover:text-[var(--ink)]">Mở trang</a>
                </div>
            </div>
        @endif

        <div class="px-3 py-3 border-t border-[var(--line)] space-y-0.5">
            @if(auth()->user()?->isPlatformAdmin())
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm text-[var(--ink-2)] hover:bg-stone-50">
                    <i class="fa-solid fa-user-shield w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>Quản trị hệ thống
                </a>
            @endif
            <button type="button" x-data @click="$dispatch('net-shortcuts')" class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm text-[var(--ink-2)] hover:bg-stone-50">
                <i class="fa-regular fa-keyboard w-4 text-center text-[var(--muted)]" aria-hidden="true"></i><span class="flex-1 text-left">Phím tắt</span><kbd class="kbd-hint">?</kbd>
            </button>
            <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ request()->routeIs('settings.*') ? 'bg-stone-100 font-semibold' : 'text-[var(--ink-2)] hover:bg-stone-50' }}">
                <i class="fa-solid fa-gear w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>Cài đặt studio
            </a>
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ request()->routeIs('profile.*') ? 'bg-stone-100 font-semibold' : 'text-[var(--ink-2)] hover:bg-stone-50' }}">
                @if($studio?->avatarUrl())
                    <img src="{{ $studio->avatarUrl() }}" alt="" class="w-4 h-4 rounded-full object-cover">
                @else
                    <i class="fa-solid fa-circle-user w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>
                @endif
                <span class="truncate">{{ auth()->user()?->name }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm text-[var(--ink-2)] hover:bg-stone-50">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>Đăng xuất
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 md:min-h-0">
        {{-- Thanh trên (mobile) --}}
        <header class="md:hidden sticky top-0 z-30 flex items-center gap-3 px-4 h-14 bg-white/95 backdrop-blur border-b border-[var(--line)]" x-data="{ open: false }">
            <a href="{{ route('today') }}" class="flex items-center gap-2 min-w-0" aria-label="Nét — Hôm nay">
                <img src="/favicon.svg" alt="" class="w-7 h-7">
                <span class="text-sm font-semibold truncate">{{ $studio?->name ?? 'Nét' }}</span>
            </a>
            <button type="button" class="ml-auto w-9 h-9 rounded-md hover:bg-stone-100 flex items-center justify-center" @click="$dispatch('net-cmdk')" aria-label="Tìm nhanh">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <button type="button" class="w-9 h-9 rounded-md hover:bg-stone-100 flex items-center justify-center" @click="open = !open" :aria-expanded="open.toString()" aria-label="Mở menu">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div x-show="open" x-cloak x-transition.opacity @click.outside="open = false"
                 class="absolute right-3 top-12 w-64 max-h-[75vh] overflow-y-auto bg-white rounded-lg border border-[var(--line)] shadow-lg py-2 z-40">
                @foreach(array_merge($nav, [['route' => 'settings.index', 'icon' => 'fa-gear', 'label' => 'Cài đặt studio'], ['route' => 'profile.edit', 'icon' => 'fa-circle-user', 'label' => 'Hồ sơ cá nhân']]) as $item)
                    <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--ink-2)] hover:bg-stone-50">
                        <i class="fa-solid {{ $item['icon'] }} w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>{{ $item['label'] }}
                    </a>
                @endforeach
                @if(auth()->user()?->isPlatformAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--ink-2)] hover:bg-stone-50">
                        <i class="fa-solid fa-user-shield w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>Quản trị hệ thống
                    </a>
                @endif
                @if($studio)
                    <a href="{{ $studio->orderFormUrl() }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--ink-2)] hover:bg-stone-50">
                        <i class="fa-solid fa-arrow-up-right-from-square w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>Trang đặt đơn của bạn
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="border-t border-[var(--line)] mt-1 pt-1">@csrf
                    <button class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 text-left"><i class="fa-solid fa-arrow-right-from-bracket w-4 text-center" aria-hidden="true"></i>Đăng xuất</button>
                </form>
            </div>
        </header>

        {{-- Thanh trên (desktop) --}}
        <header class="hidden md:flex h-16 shrink-0 items-center gap-4 px-8 bg-white border-b border-[var(--line)]">
            <h1 class="font-display text-lg font-bold tracking-tight truncate">@yield('page-title', 'Nét')</h1>
            <div class="ml-auto flex items-center gap-2">@yield('actions')</div>
        </header>

        @if(session('status'))
            <div class="bg-emerald-50 text-emerald-800 text-sm font-medium px-4 md:px-8 py-2.5 border-b border-emerald-100 flex items-center gap-2" role="status">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ session('status') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="bg-amber-50 text-amber-900 text-sm font-medium px-4 md:px-8 py-2.5 border-b border-amber-100 flex items-center gap-2" role="status">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>{{ session('warning') }}
            </div>
        @endif

        {{-- relative: phần tử absolute bên trong không "lọt" ra ngoài vùng cuộn (tránh 2 thanh cuộn). --}}
        <main class="relative flex-1 md:min-h-0 md:overflow-y-auto pb-24 md:pb-0">
            @yield('content')
        </main>
    </div>

    {{-- Thanh dưới (mobile): Hôm nay · Bảng việc · [+ Đơn mới] · Khách · Lịch --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-[var(--line)] grid grid-cols-5" style="padding-bottom: env(safe-area-inset-bottom);" aria-label="Điều hướng nhanh">
        @foreach([$nav[0], $nav[1], 'new', $nav[4], $nav[3]] as $item)
            @if($item === 'new')
                <a href="{{ route('orders.create') }}" class="flex items-center justify-center" aria-label="Tạo đơn mới">
                    <span class="w-12 h-12 -mt-5 rounded-full bg-[var(--accent)] text-[var(--on-accent)] flex items-center justify-center text-lg shadow-lg ring-4 ring-white"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                </a>
            @else
                @php $on = $isActive($item); @endphp
                <a href="{{ route($item['route']) }}" class="relative flex flex-col items-center justify-center gap-0.5 py-2 text-[0.7rem] font-medium {{ $on ? 'text-[var(--accent-ink)]' : 'text-[var(--muted)]' }}" @if($on) aria-current="page" @endif>
                    <i class="fa-solid {{ $item['icon'] }} text-base" aria-hidden="true"></i>{{ $item['label'] === 'Lịch hạn chót' ? 'Lịch' : $item['label'] }}
                    @if(($item['badge'] ?? 0) > 0)<span class="absolute top-1 right-[calc(50%-1.4rem)] min-w-4 h-4 px-1 rounded-full text-[0.6rem] font-bold flex items-center justify-center {{ $item['badge_tone'] === 'red' ? 'bg-red-500 text-white' : 'bg-[var(--accent)] text-[var(--on-accent)]' }}">{{ $item['badge'] }}</span>@endif
                </a>
            @endif
        @endforeach
    </nav>
</div>
@if($shortcutRoutes)
    @include('layouts._shortcuts')
@endif
</body>
</html>
