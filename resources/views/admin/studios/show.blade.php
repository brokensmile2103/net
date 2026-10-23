@extends('layouts.admin')
@section('title', $studio->name.' · Quản trị Nét')
@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Danh sách studio</a>
    <div class="flex flex-wrap items-center gap-3 mt-3 mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight">{{ $studio->name }}</h1>
        <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $studio->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">{{ $studio->is_active ? 'Hoạt động' : 'Đã khoá' }}</span>
        <form method="POST" action="{{ route('admin.studios.toggle', $studio->id) }}" class="ml-auto" onsubmit="return confirm({{ \Illuminate\Support\Js::from(($studio->is_active ? 'Tạm khoá' : 'Mở lại').' studio này?') }})">
            @csrf @method('PUT')
            <x-button size="sm" :variant="$studio->is_active ? 'danger' : 'success'">{{ $studio->is_active ? 'Tạm khoá studio' : 'Mở lại studio' }}</x-button>
        </form>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <section class="rounded-lg border border-[var(--line)] bg-white p-5">
            <h2 class="text-sm font-semibold mb-3">Thông tin</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-[var(--muted)]">Chủ studio</dt><dd>{{ $studio->owner?->name ?? '(đã xoá)' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--muted)]">SĐT đăng nhập</dt><dd class="tnum">{{ $studio->owner?->phone }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--muted)]">Email</dt><dd>{{ $studio->owner?->email ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--muted)]">Trang đặt đơn</dt><dd><a href="{{ $studio->orderFormUrl() }}" target="_blank" rel="noopener" class="underline">/dat/{{ $studio->slug }}</a></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--muted)]">Đăng ký</dt><dd>{{ $studio->created_at->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-[var(--muted)]">Hoạt động gần nhất</dt><dd>{{ $stats['last_activity'] ? $stats['last_activity']->format('d/m/Y H:i') : '—' }}</dd></div>
            </dl>
        </section>
        <section class="rounded-lg border border-[var(--line)] bg-white p-5">
            <h2 class="text-sm font-semibold mb-3">Mức sử dụng</h2>
            <dl class="grid grid-cols-2 gap-4">
                @foreach(['orders' => 'Tổng đơn', 'open_orders' => 'Đơn đang chạy', 'orders_30d' => 'Đơn mới 30 ngày', 'clients' => 'Khách', 'services' => 'Dịch vụ', 'images' => 'Ảnh đã tải'] as $k => $label)
                    <div><dt class="text-xs text-[var(--muted)]">{{ $label }}</dt><dd class="font-display text-xl font-bold tnum">{{ $stats[$k] }}</dd></div>
                @endforeach
            </dl>
            <div class="mt-5">
                <div class="flex justify-between text-sm"><span>Dung lượng ảnh</span><span class="tnum text-[var(--muted)]">{{ round($studio->storage_bytes / 1048576, 1) }} / {{ round($studio->storageQuotaBytes() / 1048576) }} MB</span></div>
                <div class="h-2 rounded-full bg-stone-100 mt-2"><div class="h-full rounded-full bg-[var(--ink)]" style="width: {{ $studio->storageUsedPercent() }}%"></div></div>
            </div>
        </section>
    </div>
    <p class="text-xs text-[var(--muted)] mt-6">Trang này chỉ hiện số lượng để hỗ trợ vận hành — nội dung đơn, brief, khách hàng và tiền của studio không hiển thị với quản trị viên.</p>
@endsection
