@extends('layouts.app')
@section('title', 'Báo cáo · Nét')
@section('page-title', 'Báo cáo')
@section('actions')
    <x-button size="sm" variant="outline" icon="fa-file-csv" :href="route('reports.export')">Xuất CSV</x-button>
@endsection
@section('content')
@php
    $prev = $month->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $month->copy()->addMonthNoOverflow();
    $max = max(1, (float) $trend->max('value'));
    $serviceMax = max(1, (float) $byService->max('total'));
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto">
    <div class="flex items-center gap-1 mb-5">
        <a href="{{ route('reports.index', ['thang' => $prev]) }}" class="w-9 h-9 rounded-md hover:bg-white flex items-center justify-center" aria-label="Tháng trước"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
        <h2 class="font-display text-xl font-bold tracking-tight w-40 text-center">Tháng {{ $month->format('m/Y') }}</h2>
        @if($next->startOfMonth()->lte(now()))
            <a href="{{ route('reports.index', ['thang' => $next->format('Y-m')]) }}" class="w-9 h-9 rounded-md hover:bg-white flex items-center justify-center" aria-label="Tháng sau"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        @else
            <span class="w-9 h-9"></span>
        @endif
    </div>

    {{-- Con số chính --}}
    <div class="rounded-lg border border-[var(--line)] bg-white grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-[var(--line)]">
        <div class="px-5 py-4 col-span-2 md:col-span-1">
            <p class="text-xs text-[var(--muted)]">Thực thu</p>
            <p class="font-display text-3xl font-bold tnum mt-0.5">{{ money($r['collected']) }}<span class="text-lg">đ</span></p>
            <p class="text-xs text-[var(--muted)] mt-1">{{ $r['payments'] }} lần thu tiền</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs text-[var(--muted)]">Đơn mới nhận</p>
            <p class="font-display text-3xl font-bold tnum mt-0.5">{{ $r['new_orders'] }}</p>
            <p class="text-xs text-[var(--muted)] mt-1">Đang chạy: {{ $r['open'] }}</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs text-[var(--muted)]">Đã bàn giao</p>
            <p class="font-display text-3xl font-bold tnum mt-0.5">{{ $r['delivered'] }}</p>
            <p class="text-xs text-[var(--muted)] mt-1">
                @if($r['on_time_rate'] !== null) Đúng hạn {{ $r['on_time_rate'] }}% @else Chưa có đơn đặt hạn @endif
            </p>
        </div>
        <div class="px-5 py-4 col-span-2 md:col-span-1">
            <p class="text-xs text-[var(--muted)]">Còn phải thu (tất cả đơn)</p>
            <p class="font-display text-3xl font-bold tnum mt-0.5 {{ $r['receivable'] > 0 ? 'text-red-600' : '' }}">{{ money($r['receivable']) }}<span class="text-lg">đ</span></p>
            @if($r['receivable'] > 0)<a href="{{ route('orders.index', ['loc' => 'unpaid']) }}" class="text-xs text-[var(--accent-ink)] font-semibold mt-1 inline-block">Xem đơn còn nợ →</a>@endif
        </div>
    </div>

    @if($r['avg_rounds'] !== null && $r['avg_rounds'] > 0)
        <p class="text-sm text-[var(--muted)] mt-3"><i class="fa-solid fa-layer-group mr-1.5" aria-hidden="true"></i>Đơn bàn giao tháng này trung bình qua <span class="font-semibold text-[var(--ink)]">{{ str_replace('.', ',', $r['avg_rounds']) }}</span> vòng gửi bản thảo.</p>
    @endif

    {{-- 12 tháng --}}
    <section class="rounded-lg border border-[var(--line)] bg-white p-5 mt-6" aria-labelledby="h-trend">
        <h3 id="h-trend" class="text-sm font-semibold">Thực thu 12 tháng</h3>
        <div class="mt-5 flex items-end gap-1.5 sm:gap-2 h-44" role="list">
            @foreach($trend as $t)
                @php $h = $t['value'] > 0 ? max(3, round($t['value'] / $max * 100)) : 0; $isCur = $t['key'] === $month->format('Y-m'); @endphp
                <a href="{{ route('reports.index', ['thang' => $t['key']]) }}" role="listitem" class="group flex-1 h-full flex flex-col justify-end items-center min-w-0"
                   title="{{ $t['label'] }}/{{ $t['year'] }}: {{ money($t['value']) }}đ" aria-label="Tháng {{ $t['label'] }}/{{ $t['year'] }}: {{ money($t['value']) }} đồng">
                    <span class="text-[0.62rem] tnum text-[var(--muted)] mb-1 opacity-0 group-hover:opacity-100 {{ $isCur ? 'opacity-100 font-semibold text-[var(--ink)]' : '' }} hidden sm:block">{{ $t['value'] > 0 ? money_compact($t['value']) : '' }}</span>
                    <span class="w-full rounded-t {{ $isCur ? 'bg-[var(--accent)]' : 'bg-stone-300 group-hover:bg-stone-400' }}" style="height: {{ $h }}%"></span>
                </a>
            @endforeach
        </div>
        <div class="flex gap-1.5 sm:gap-2 mt-1.5 border-t border-[var(--line)] pt-1.5">
            @foreach($trend as $t)
                <span class="flex-1 text-center text-[0.65rem] tnum {{ $t['key'] === $month->format('Y-m') ? 'font-bold text-[var(--ink)]' : 'text-[var(--muted)]' }}">{{ $t['label'] }}</span>
            @endforeach
        </div>
    </section>

    <div class="grid md:grid-cols-2 gap-6 mt-6">
        <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-svc">
            <h3 id="h-svc" class="text-sm font-semibold">Theo dịch vụ <span class="font-normal text-[var(--muted)]">· tháng {{ $month->month }}</span></h3>
            @if($byService->isEmpty())
                <p class="text-sm text-[var(--muted)] mt-4">Chưa thu tiền đơn nào trong tháng.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach($byService as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate">{{ $row->name }} <span class="text-xs text-[var(--muted)]">· {{ $row->orders }} đơn</span></span>
                                <span class="tnum font-semibold">{{ money($row->total) }}đ</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-stone-100 mt-1.5"><div class="h-full rounded-full bg-[var(--accent)]" style="width: {{ round($row->total / $serviceMax * 100) }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-top">
            <h3 id="h-top" class="text-sm font-semibold">Khách trả nhiều nhất <span class="font-normal text-[var(--muted)]">· 12 tháng</span></h3>
            @if($topClients->isEmpty())
                <p class="text-sm text-[var(--muted)] mt-4">Chưa có dữ liệu.</p>
            @else
                <ol class="mt-3 divide-y divide-[var(--line)]">
                    @foreach($topClients as $i => $c)
                        <li>
                            <a href="{{ route('clients.show', $c->id) }}" class="flex items-center gap-3 py-2.5 hover:text-[var(--accent-ink)]">
                                <span class="font-display font-bold text-[var(--muted)] w-4 tnum">{{ $i + 1 }}</span>
                                <span class="flex-1 truncate text-sm">{{ $c->name }} <span class="text-xs text-[var(--muted)]">· {{ $c->orders }} đơn</span></span>
                                <span class="tnum text-sm font-semibold">{{ money_compact($c->total) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
@endsection
