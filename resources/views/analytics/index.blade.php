@extends('layouts.app')
@section('title', 'Thống kê trang đặt đơn · Nét')
@section('page-title', 'Thống kê trang đặt đơn')
@section('actions')
    <x-button size="sm" variant="outline" icon="fa-arrow-up-right-from-square" :href="$studio->orderFormUrl()" target="_blank">Mở trang</x-button>
@endsection
@section('content')
@php
    $t = $r['totals'];
    $daily = collect($r['daily']);
    $maxV = max(1, $daily->max('visitors'));
    $maxO = max(1, $daily->max('orders'));
    $fmtDelta = function (?int $d) {
        if ($d === null) return '';
        $cls = $d > 0 ? 'text-emerald-700' : ($d < 0 ? 'text-red-600' : 'text-[var(--muted)]');
        $arrow = $d > 0 ? 'fa-arrow-trend-up' : ($d < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
        return '<span class="'.$cls.' font-semibold"><i class="fa-solid '.$arrow.' mr-0.5" aria-hidden="true"></i>'.($d > 0 ? '+' : '').$d.'%</span> so với '.e('kỳ trước');
    };
    $tagged = ['zalo' => 'Zalo', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'email' => 'Email'];
    $kpis = [
        ['key' => 'visitors', 'label' => 'Người xem', 'value' => number_format($t['visitors'], 0, ',', '.'), 'hint' => number_format($t['views'], 0, ',', '.').' lượt xem'],
        ['key' => 'orders', 'label' => 'Đơn gửi qua trang', 'value' => $t['orders'], 'hint' => $t['value'] > 0 ? 'Báo giá '.money($t['value']).'đ' : 'Chưa có báo giá'],
        ['key' => 'rate', 'label' => 'Tỉ lệ chuyển đổi', 'value' => str_replace('.', ',', (string) $t['rate']).'%', 'hint' => 'Đơn + hẹn / người xem'],
        ['key' => 'consults', 'label' => 'Hẹn gọi tư vấn', 'value' => $t['consults'], 'hint' => $studio->consultEnabled() ? 'Đặt qua trang' : 'Đang tắt'],
    ];
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg border border-[var(--line)] bg-white p-0.5" role="group" aria-label="Khoảng thời gian">
            @foreach(config('net.analytics.ranges') as $d)
                <a href="{{ route('analytics.index', ['ngay' => $d]) }}" class="px-3 py-1.5 text-sm rounded-md {{ $r['days'] === $d ? 'bg-[var(--ink)] text-white font-semibold' : 'text-[var(--ink-2)] hover:bg-stone-50' }}" @if($r['days'] === $d) aria-current="page" @endif>{{ $d }} ngày</a>
            @endforeach
        </div>
        <p class="text-sm text-[var(--muted)]">{{ $r['from']->format('d/m') }} – {{ $r['to']->format('d/m/Y') }}</p>
    </div>

    @unless($r['has_any'])
        <div class="rounded-lg border border-dashed border-[var(--line-strong)] bg-white p-5 text-sm">
            <p class="font-semibold"><i class="fa-solid fa-seedling text-[var(--accent)] mr-1.5" aria-hidden="true"></i>Chưa có lượt xem nào được ghi nhận</p>
            <p class="text-[var(--muted)] mt-1">Nét bắt đầu đếm từ bây giờ — chia sẻ link trang đặt đơn lên Zalo, Facebook, Instagram… rồi quay lại đây xem khách đến từ đâu và bao nhiêu người gửi đơn. Bạn tự mở trang khi đang đăng nhập sẽ không bị tính.</p>
        </div>
    @endunless

    {{-- Con số chính --}}
    <div class="rounded-lg border border-[var(--line)] bg-white grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-[var(--line)]">
        @foreach($kpis as $k)
            <div class="px-5 py-4">
                <p class="text-xs text-[var(--muted)]">{{ $k['label'] }}</p>
                <p class="font-display text-3xl font-bold tnum mt-0.5">{{ $k['value'] }}</p>
                <p class="text-xs text-[var(--muted)] mt-1">{{ $k['hint'] }}</p>
                @if(($r['delta'][$k['key']] ?? null) !== null)<p class="text-xs mt-1">{!! $fmtDelta($r['delta'][$k['key']]) !!}</p>@endif
            </div>
        @endforeach
    </div>

    {{-- Theo ngày: 2 biểu đồ cùng trục ngày (không dùng 2 trục y) --}}
    <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-daily" x-data="{ tip: null }">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="h-daily" class="font-display text-lg font-bold tracking-tight">Theo ngày</h2>
            <p class="text-xs text-[var(--muted)] h-4" x-text="tip ?? 'Rê chuột / chạm vào cột để xem số'"></p>
        </div>
        @foreach([['visitors', 'Người xem', $maxV, 'bg-[var(--accent)]'], ['orders', 'Đơn gửi qua trang', $maxO, 'bg-[var(--ink)]']] as [$field, $label, $max, $color])
            <p class="text-xs font-semibold text-[var(--ink-2)] mt-4 mb-1.5">{{ $label }} <span class="font-normal text-[var(--muted)]">· cao nhất {{ $daily->max($field) }}</span></p>
            <div class="flex items-end gap-[2px] {{ $field === 'visitors' ? 'h-36' : 'h-16' }} border-b border-[var(--line)]" role="img" aria-label="{{ $label }} mỗi ngày, {{ $r['days'] }} ngày gần nhất">
                @foreach($daily as $d)
                    @php $h = $d[$field] > 0 ? max(3, round($d[$field] / $max * 100)) : 0; $text = $d['day']->format('d/m').': '.$d['visitors'].' người xem · '.$d['views'].' lượt · '.$d['orders'].' đơn'; @endphp
                    <div class="flex-1 h-full flex items-end group cursor-default" @mouseenter="tip = @js($text)" @mouseleave="tip = null" @click="tip = @js($text)" title="{{ $text }}">
                        <div class="w-full rounded-t-[4px] {{ $color }} {{ $d[$field] ? 'opacity-90 group-hover:opacity-100' : '' }}" style="height: {{ $h }}%"></div>
                    </div>
                @endforeach
            </div>
        @endforeach
        <div class="flex justify-between text-[11px] text-[var(--muted)] mt-1 tnum" aria-hidden="true">
            <span>{{ $r['from']->format('d/m') }}</span><span>{{ $r['from']->copy()->addDays(intdiv($r['days'], 2))->format('d/m') }}</span><span>{{ $r['to']->format('d/m') }}</span>
        </div>
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-xs font-semibold text-[var(--muted)] hover:text-[var(--ink)]">Xem dạng bảng</summary>
            <div class="mt-2 max-h-64 overflow-y-auto">
                <table class="w-full text-sm tnum">
                    <thead><tr class="text-left text-xs text-[var(--muted)]"><th class="py-1 font-medium">Ngày</th><th class="py-1 font-medium text-right">Người xem</th><th class="py-1 font-medium text-right">Lượt xem</th><th class="py-1 font-medium text-right">Đơn</th></tr></thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach($daily->reverse() as $d)
                            <tr><td class="py-1">{{ $d['day']->format('d/m') }}</td><td class="py-1 text-right">{{ $d['visitors'] }}</td><td class="py-1 text-right">{{ $d['views'] }}</td><td class="py-1 text-right">{{ $d['orders'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <div class="grid md:grid-cols-5 gap-6">
        {{-- Nguồn --}}
        <section class="md:col-span-3 rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-src">
            <h2 id="h-src" class="font-display text-lg font-bold tracking-tight">Khách đến từ đâu</h2>
            @if($r['sources'])
                @php $srcMax = max(1, collect($r['sources'])->max('visitors')); @endphp
                <table class="w-full mt-3 text-sm">
                    <thead><tr class="text-left text-xs text-[var(--muted)]"><th class="py-1.5 font-medium">Nguồn</th><th class="py-1.5 font-medium text-right">Người xem</th><th class="py-1.5 font-medium text-right">Đơn + hẹn</th><th class="py-1.5 font-medium text-right">Tỉ lệ</th></tr></thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach($r['sources'] as $s)
                            <tr>
                                <td class="py-2 pr-2">
                                    <span class="flex items-center gap-2"><i class="{{ $s['icon'] }} w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>{{ $s['label'] }}</span>
                                    <span class="block h-1 rounded-full bg-stone-100 mt-1.5" aria-hidden="true"><span class="block h-full rounded-full bg-[var(--accent)]" style="width: {{ round($s['visitors'] / $srcMax * 100) }}%"></span></span>
                                </td>
                                <td class="py-2 text-right tnum">{{ $s['visitors'] }}</td>
                                <td class="py-2 text-right tnum">{{ $s['orders'] }}</td>
                                <td class="py-2 text-right tnum">{{ $s['visitors'] ? str_replace('.', ',', (string) $s['rate']).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-[var(--muted)] mt-2">Chưa có dữ liệu trong khoảng này.</p>
            @endif
            <p class="text-xs text-[var(--muted)] mt-3">Facebook, Messenger, Zalo, Instagram, TikTok mở link bằng trình duyệt riêng nên Nét nhận ra được. Link dán vào nơi khác (bio, nhóm chat, email) → dùng link gắn nguồn bên dưới để biết chính xác.</p>
        </section>

        <div class="md:col-span-2 space-y-6">
            {{-- Phễu --}}
            <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-funnel">
                <h2 id="h-funnel" class="font-display text-lg font-bold tracking-tight">Từ người xem tới bàn giao</h2>
                @php $top = max(1, $r['funnel'][0]['value']); @endphp
                <ol class="mt-3 space-y-2.5">
                    @foreach($r['funnel'] as $i => $f)
                        <li>
                            <div class="flex justify-between text-sm"><span>{{ $f['label'] }}</span><span class="tnum font-semibold">{{ $f['value'] }}</span></div>
                            <div class="h-2 rounded-full bg-stone-100 mt-1" aria-hidden="true"><div class="h-full rounded-full {{ $i === 0 ? 'bg-[var(--accent)]' : 'bg-[var(--ink)]' }}" style="width: {{ $f['value'] > 0 ? max(2, round($f['value'] / $top * 100)) : 0 }}%"></div></div>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Thiết bị --}}
            <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-dev">
                <h2 id="h-dev" class="font-display text-lg font-bold tracking-tight">Thiết bị</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach($r['devices'] as $d)
                        <li class="flex items-center gap-3">
                            <span class="w-28 shrink-0">{{ $d['label'] }}</span>
                            <span class="flex-1 h-2 rounded-full bg-stone-100" aria-hidden="true"><span class="block h-full rounded-full bg-[var(--accent)]" style="width: {{ $d['share'] }}%"></span></span>
                            <span class="w-10 text-right tnum">{{ $d['share'] }}%</span>
                        </li>
                    @endforeach
                </ul>
                @if(($r['devices'][0]['share'] ?? 0) >= 60)
                    <p class="text-xs text-[var(--muted)] mt-3">Phần lớn khách xem bằng điện thoại — ảnh tác phẩm dọc/vuông và câu chào ngắn sẽ hiển thị đẹp hơn.</p>
                @endif
            </section>
        </div>
    </div>

    @if($r['services'])
        <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-svc">
            <h2 id="h-svc" class="font-display text-lg font-bold tracking-tight">Dịch vụ được đặt qua trang</h2>
            <ul class="mt-3 grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @foreach($r['services'] as $s)
                    <li class="flex justify-between gap-3 border-b border-[var(--line)] pb-2"><span class="truncate">{{ $s['name'] }}</span><span class="tnum font-semibold">{{ $s['orders'] }} đơn</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Link gắn nguồn --}}
    <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-links" x-data="{ copied: null }">
        <h2 id="h-links" class="font-display text-lg font-bold tracking-tight">Link gắn nguồn</h2>
        <p class="text-sm text-[var(--muted)] mt-0.5">Mỗi nơi chia sẻ dùng 1 link riêng để Nét đếm đúng nguồn. Khách thấy vẫn là trang đặt đơn như bình thường.</p>
        <ul class="mt-3 divide-y divide-[var(--line)]">
            @foreach($tagged as $key => $label)
                @php $url = $studio->orderFormUrl().'?nguon='.$key; @endphp
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                    <span class="w-24 text-sm font-medium">{{ $label }}</span>
                    <code class="flex-1 min-w-0 truncate text-xs font-mono text-[var(--ink-2)]">{{ $url }}</code>
                    <button type="button" class="text-xs font-semibold text-[var(--accent-ink)] hover:underline" @click="copyText(@js($url)).then(() => { copied = @js($key); setTimeout(() => copied = null, 1500) })" x-text="copied === @js($key) ? 'Đã chép' : 'Sao chép'">Sao chép</button>
                </li>
            @endforeach
        </ul>
        <p class="text-xs text-[var(--muted)] mt-3">
            Muốn số liệu sâu hơn (thời gian xem, khu vực, quảng cáo)? Gắn <a href="{{ route('settings.index') }}#trang-dat-don-ga" class="font-semibold text-[var(--accent-ink)] hover:underline">Google Analytics</a> của riêng bạn.
            Người xem được tính 1 lần mỗi ngày, không dùng cookie và không lưu địa chỉ IP.
        </p>
    </section>
</div>
@endsection
