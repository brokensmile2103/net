@extends('layouts.app')
@section('title', 'Tất cả đơn · Nét')
@section('page-title', 'Tất cả đơn')
@section('actions')
    <x-button size="sm" variant="outline" icon="fa-file-csv" :href="route('reports.export')">Xuất CSV</x-button>
    <x-button size="sm" icon="fa-plus" :href="route('orders.create')">Đơn mới</x-button>
@endsection
@section('content')
@php
    $tabs = ['open' => 'Đang chạy', '' => 'Tất cả', 'new' => 'Chờ nhận', 'review' => 'Chờ khách xem', 'overdue' => 'Trễ hạn', 'unpaid' => 'Còn nợ', 'delivered' => 'Đã bàn giao', 'cancelled' => 'Đã huỷ'];
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-6xl mx-auto">
    <form method="GET" class="flex gap-2 mb-4">
        <input type="hidden" name="loc" value="{{ $filter }}">
        <label for="q" class="sr-only">Tìm đơn</label>
        <div class="relative flex-1 max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm" aria-hidden="true"></i>
            <input id="q" data-search-focus name="q" value="{{ $q }}" placeholder="Mã đơn, tên đơn, tên hoặc SĐT khách" class="w-full rounded-lg border border-[var(--line-strong)] bg-white pl-9 pr-3 py-2 text-sm">
        </div>
        <x-button size="sm" variant="ink">Tìm</x-button>
    </form>

    <nav class="flex gap-1.5 overflow-x-auto scrollbar-hide mb-5" aria-label="Lọc đơn">
        @foreach($tabs as $k => $label)
            @php $on = (string) $filter === (string) $k; @endphp
            <a href="{{ route('orders.index', array_filter(['loc' => $k, 'q' => $q])) }}" @if($on) aria-current="page" @endif
               class="whitespace-nowrap rounded-full px-3 py-1.5 text-sm {{ $on ? 'bg-[var(--ink)] text-white font-semibold' : 'bg-white ring-1 ring-[var(--line)] text-[var(--ink-2)] hover:ring-[var(--line-strong)]' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($orders->isEmpty())
        <div class="rounded-lg border border-dashed border-[var(--line-strong)] bg-white px-6 py-12 text-center">
            <p class="font-semibold">{{ $q !== '' || $filter ? 'Không có đơn nào khớp.' : 'Chưa có đơn nào.' }}</p>
            <p class="text-sm text-[var(--muted)] mt-1">Gửi link đặt đơn cho khách, hoặc <a href="{{ route('orders.create') }}" class="underline">tự tạo đơn</a> từ cuộc trò chuyện Zalo.</p>
        </div>
    @else
        <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
            @foreach($orders as $order)
                <a href="{{ route('orders.show', $order->id) }}" class="flex items-center gap-4 px-4 py-3 hover:bg-stone-50">
                    <x-cover :order="$order" ratio="1 / 1" :crop="false" class="w-12 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold truncate"><span class="text-[var(--muted)] font-medium tnum mr-1.5">{{ $order->code }}</span>{{ $order->title }}</p>
                        <p class="text-xs text-[var(--muted)] truncate mt-0.5">{{ $order->client?->name }}@if($order->service) · {{ $order->service->name }}@endif</p>
                    </div>
                    <div class="hidden sm:block w-36 text-xs text-right {{ $order->isOverdue() ? 'text-red-600 font-semibold' : 'text-[var(--muted)]' }}">{{ $order->dueLabel() }}</div>
                    <div class="hidden md:block w-28 text-right text-sm tnum">
                        {{ (float) $order->total_amount > 0 ? money($order->total_amount).'đ' : '—' }}
                        @if($order->balance() > 0 && ! $order->isCancelled())<p class="text-[0.7rem] text-red-600">còn {{ money_compact($order->balance()) }}</p>@endif
                    </div>
                    <x-status :order="$order" class="shrink-0" />
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
