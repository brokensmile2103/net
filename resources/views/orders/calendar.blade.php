@extends('layouts.app')
@section('title', 'Lịch hạn chót · Nét')
@section('page-title', 'Lịch hạn chót')
@section('content')
@php
    $prev = $month->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $month->copy()->addMonthNoOverflow()->format('Y-m');
    $today = now()->toDateString();
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-6xl mx-auto">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex items-center gap-1">
            <a href="{{ route('orders.calendar', ['thang' => $prev]) }}" class="w-9 h-9 rounded-md hover:bg-white flex items-center justify-center" aria-label="Tháng trước"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
            <h2 class="font-display text-xl font-bold tracking-tight w-40 text-center">Tháng {{ $month->format('m/Y') }}</h2>
            <a href="{{ route('orders.calendar', ['thang' => $next]) }}" class="w-9 h-9 rounded-md hover:bg-white flex items-center justify-center" aria-label="Tháng sau"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        </div>
        @unless($month->isSameMonth(now()))<a href="{{ route('orders.calendar') }}" class="text-sm text-[var(--accent-ink)] font-semibold">Về tháng này</a>@endunless
        @if($undated > 0)<a href="{{ route('orders.index', ['loc' => 'open']) }}" class="ml-auto text-sm text-[var(--muted)] hover:text-[var(--ink)]">{{ $undated }} đơn đang chạy chưa đặt hạn</a>@endif
    </div>

    {{-- Desktop: lưới tháng --}}
    <div class="hidden md:grid grid-cols-7 rounded-lg border border-[var(--line)] bg-white overflow-hidden">
        @foreach(['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $d)
            <div class="px-2 py-2 text-xs font-semibold text-[var(--muted)] border-b border-[var(--line)]">{{ $d }}</div>
        @endforeach
        @for($day = $start->copy(); $day->lte($end); $day->addDay())
            @php $key = $day->toDateString(); $list = $orders->get($key, collect()); @endphp
            <div class="min-h-28 p-1.5 border-b border-r border-[var(--line)] [&:nth-child(7n)]:border-r-0 {{ $day->month !== $month->month ? 'bg-stone-50/70' : '' }}">
                <p class="text-xs mb-1 tnum {{ $key === $today ? 'inline-flex w-6 h-6 items-center justify-center rounded-full bg-[var(--accent)] text-[var(--on-accent)] font-bold' : ($day->month !== $month->month ? 'text-stone-300' : 'text-[var(--muted)]') }}">{{ $day->day }}</p>
                @foreach($list->take(4) as $o)
                    <a href="{{ route('orders.show', $o->id) }}" class="block truncate rounded px-1.5 py-0.5 mb-0.5 text-[0.72rem] {{ $o->isOpen() ? ($o->isOverdue() ? 'bg-red-50 text-red-700' : 'bg-[var(--accent-soft)] text-[var(--accent-ink)]') : 'bg-stone-100 text-[var(--muted)] line-through' }}" title="{{ $o->code }} · {{ $o->title }} · {{ $o->statusLabel() }}">{{ $o->title }}</a>
                @endforeach
                @if($list->count() > 4)<p class="text-[0.7rem] text-[var(--muted)] px-1">+{{ $list->count() - 4 }} đơn</p>@endif
            </div>
        @endfor
    </div>

    {{-- Mobile: danh sách theo ngày --}}
    <div class="md:hidden space-y-4">
        @forelse($orders as $date => $list)
            @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
            @continue($d->month !== $month->month)
            <div>
                <p class="text-sm font-semibold mb-1.5 {{ $date === $today ? 'text-[var(--accent-ink)]' : '' }}">{{ day_label($d) }}</p>
                <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                    @foreach($list as $o)
                        <a href="{{ route('orders.show', $o->id) }}" class="flex items-center gap-3 px-3 py-2.5">
                            <span class="text-xs text-[var(--muted)] tnum w-12">{{ $o->code }}</span>
                            <span class="text-sm flex-1 truncate {{ $o->isOpen() ? '' : 'line-through text-[var(--muted)]' }}">{{ $o->title }}</span>
                            <x-status :order="$o" />
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-[var(--muted)] text-center py-10">Không có hạn chót nào trong tháng này.</p>
        @endforelse
    </div>
</div>
@endsection
