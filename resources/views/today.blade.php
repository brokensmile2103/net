@extends('layouts.app')
@section('title', 'Hôm nay · Nét')
@section('page-title', 'Hôm nay')
@section('actions')
    <x-button size="sm" icon="fa-plus" :href="route('orders.create')">Đơn mới</x-button>
@endsection
@section('content')
@php
    $row = function ($order, $meta = null) {
        return view('orders._row', ['order' => $order, 'meta' => $meta])->render();
    };
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto">
    <div class="mb-6">
        <h2 class="font-display text-2xl md:text-3xl font-bold tracking-tight">Chào {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->last() }},</h2>
        <p class="text-[var(--muted)] mt-1">{{ day_label(now()) }}, {{ now()->format('d/m/Y') }}.
            @if($overdue->isEmpty() && $newOrders->isEmpty() && $dueSoon->isEmpty() && $revising->isEmpty())
                Không có gì gấp — một ngày yên ổn để làm cho tới.
            @endif
        </p>
    </div>

    <x-errors class="mb-5" />

    @if($isEmpty)
        {{-- Bắt đầu --}}
        <section class="rounded-lg border border-[var(--line)] bg-white p-5 md:p-6 mb-6">
            <h3 class="font-display text-xl font-bold tracking-tight">Bắt đầu với Nét</h3>
            <ol class="mt-4 space-y-4">
                <li class="flex gap-3"><span class="font-display font-bold text-[var(--accent)] w-5">1</span><div><p class="font-semibold">Kiểm tra bảng giá</p><p class="text-sm text-[var(--muted)]">Nét đã tạo sẵn 3 dịch vụ mẫu. <a href="{{ route('services.index') }}" class="underline">Sửa lại theo giá của bạn</a> — khách sẽ thấy ở trang đặt đơn.</p></div></li>
                <li class="flex gap-3"><span class="font-display font-bold text-[var(--accent)] w-5">2</span><div><p class="font-semibold">Thêm thông tin nhận tiền & quy định</p><p class="text-sm text-[var(--muted)]">Số tài khoản để hiện mã VietQR, quy định cọc / số lần sửa ở <a href="{{ route('settings.index') }}" class="underline">Cài đặt</a>.</p></div></li>
                <li class="flex gap-3"><span class="font-display font-bold text-[var(--accent)] w-5">3</span><div><p class="font-semibold">Gửi link đặt đơn cho khách</p>
                    <div class="flex flex-wrap items-center gap-2 mt-1" x-data="{ copied: false }">
                        <code class="text-sm bg-stone-100 rounded px-2 py-1 break-all">{{ $studio->orderFormUrl() }}</code>
                        <button type="button" class="text-sm font-semibold text-[var(--accent-ink)]" @click="copyText(@js($studio->orderFormUrl())).then(() => { copied = true; setTimeout(() => copied = false, 1500) })" x-text="copied ? 'Đã chép' : 'Sao chép'">Sao chép</button>
                    </div>
                    <p class="text-sm text-[var(--muted)] mt-1">Dán vào bio Facebook, Instagram, Behance… Hoặc <a href="{{ route('orders.create') }}" class="underline">tự tạo đơn</a> cho khách đã nhắn qua Zalo.</p></div></li>
            </ol>
        </section>
    @endif

    <div class="grid grid-cols-3 gap-3 mb-7">
        <a href="{{ route('orders.board') }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-3 hover:border-[var(--line-strong)]">
            <p class="text-xs text-[var(--muted)]">Đang làm</p><p class="font-display text-2xl font-bold tnum">{{ $stats['open'] }}</p>
        </a>
        <a href="{{ route('reports.index') }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-3 hover:border-[var(--line-strong)]">
            <p class="text-xs text-[var(--muted)]">Đã thu tháng {{ now()->month }}</p><p class="font-display text-2xl font-bold tnum">{{ money_compact($stats['collected']) }}</p>
        </a>
        <a href="{{ route('orders.index', ['loc' => 'unpaid']) }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-3 hover:border-[var(--line-strong)]">
            <p class="text-xs text-[var(--muted)]">Còn phải thu</p><p class="font-display text-2xl font-bold tnum {{ $stats['receivable'] > 0 ? 'text-red-600' : '' }}">{{ money_compact($stats['receivable']) }}</p>
        </a>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-6">
            @if($consults->isNotEmpty())
                <section aria-labelledby="h-consult">
                    <h3 id="h-consult" class="text-sm font-semibold mb-2"><i class="fa-regular fa-calendar-check text-[var(--accent)] mr-1.5" aria-hidden="true"></i>Hẹn gọi tư vấn sắp tới <a href="{{ route('consultations.index') }}" class="ml-1 font-normal text-[var(--accent-ink)] hover:underline">Xem tất cả</a></h3>
                    <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                        @foreach($consults as $c)
                            <a href="{{ route('consultations.index') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
                                <span class="w-16 shrink-0 font-semibold tnum text-sm">{{ $c->starts_at->format('H:i') }}<span class="block text-xs font-normal text-[var(--muted)]">{{ $c->starts_at->isToday() ? 'Hôm nay' : ($c->starts_at->isTomorrow() ? 'Ngày mai' : $c->starts_at->format('d/m')) }}</span></span>
                                <span class="min-w-0 flex-1"><span class="block text-sm font-semibold truncate">{{ $c->name }}</span><span class="block text-xs text-[var(--muted)] truncate">{{ $c->channelLabel() }}{{ $c->phone ? ' · '.$c->phone : '' }}</span></span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($newOrders->isNotEmpty())
                <section aria-labelledby="h-new">
                    <h3 id="h-new" class="text-sm font-semibold mb-2"><span class="inline-block w-2 h-2 rounded-full bg-[var(--accent)] mr-2 align-middle" aria-hidden="true"></span>Đơn mới chờ nhận <span class="text-[var(--muted)] font-normal">({{ $newOrders->count() }})</span></h3>
                    <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                        @foreach($newOrders as $o) {!! $row($o, 'Gửi '.ago($o->created_at)) !!} @endforeach
                    </div>
                </section>
            @endif

            @if($overdue->isNotEmpty())
                <section aria-labelledby="h-overdue">
                    <h3 id="h-overdue" class="text-sm font-semibold mb-2 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>Trễ hạn ({{ $overdue->count() }})</h3>
                    <div class="rounded-lg border border-red-200 bg-white divide-y divide-[var(--line)]">
                        @foreach($overdue as $o) {!! $row($o) !!} @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="h-soon">
                <h3 id="h-soon" class="text-sm font-semibold mb-2">Tới hạn trong 7 ngày</h3>
                @if($dueSoon->isEmpty())
                    <p class="rounded-lg border border-dashed border-[var(--line-strong)] px-4 py-5 text-sm text-[var(--muted)] text-center">Không có đơn nào tới hạn tuần này.</p>
                @else
                    <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                        @foreach($dueSoon as $o) {!! $row($o) !!} @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section aria-labelledby="h-client">
                <h3 id="h-client" class="text-sm font-semibold mb-2">Khách vừa phản hồi</h3>
                @if($clientEvents->isEmpty())
                    <p class="rounded-lg border border-dashed border-[var(--line-strong)] px-4 py-5 text-sm text-[var(--muted)] text-center">Khi khách chọn phương án, góp ý hay duyệt, bạn sẽ thấy ở đây.</p>
                @else
                    <ol class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                        @foreach($clientEvents as $e)
                            <li>
                                <a href="{{ route('orders.show', $e->order_id) }}" class="flex gap-3 px-4 py-3 hover:bg-stone-50">
                                    <span class="w-7 h-7 rounded-full bg-[var(--accent-soft)] text-[var(--accent-ink)] flex items-center justify-center text-xs shrink-0"><i class="fa-solid {{ $e->icon() }}" aria-hidden="true"></i></span>
                                    <span class="min-w-0">
                                        <span class="block text-sm"><span class="font-semibold">{{ $e->order?->client?->name }}</span> — {{ $e->message }}</span>
                                        <span class="block text-xs text-[var(--muted)] mt-0.5">{{ $e->order?->code }} · {{ $e->created_at->format('H:i d/m') }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            @if($revising->isNotEmpty())
                <section aria-labelledby="h-rev">
                    <h3 id="h-rev" class="text-sm font-semibold mb-2">Đang sửa theo góp ý</h3>
                    <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                        @foreach($revising as $o) {!! $row($o, 'Đã sửa '.$o->revisionsUsed().'/'.$o->revision_rounds.' lần') !!} @endforeach
                    </div>
                </section>
            @endif

            @if($waiting->isNotEmpty())
                <section aria-labelledby="h-wait">
                    <h3 id="h-wait" class="text-sm font-semibold mb-2">Chờ khách xem hơn {{ $waitingDays }} ngày</h3>
                    <p class="text-xs text-[var(--muted)] -mt-1 mb-2">Có thể nhắc khách bằng tin nhắn mẫu trong trang đơn.</p>
                    <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
                        @foreach($waiting as $o) {!! $row($o, 'Gửi '.ago($o->updated_at)) !!} @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
