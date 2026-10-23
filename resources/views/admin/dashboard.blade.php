@extends('layouts.admin')
@section('title', 'Studio · Quản trị Nét')
@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight flex-1">Studio trên hệ thống</h1>
        <form method="GET">
            <label for="admin-search" class="sr-only">Tìm studio</label>
            <input type="search" name="q" id="admin-search" value="{{ $search }}" placeholder="Tên, địa chỉ, SĐT chủ studio…" autocomplete="off"
                   class="rounded-lg border border-[var(--line-strong)] bg-white py-2 px-3.5 text-sm w-full sm:w-72">
        </form>
    </div>

    @if(! $schemaOk || $schemaError)
        <div class="mb-6 rounded-lg border px-4 py-3 text-sm {{ $schemaError ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900' }}" role="alert">
            <p class="font-semibold"><i class="fa-solid fa-database mr-1.5" aria-hidden="true"></i>{{ $schemaError ? 'Cập nhật database bị lỗi' : 'Có bản cập nhật database chưa chạy' }}</p>
            @if($schemaError)<p class="mt-1 break-words font-mono text-xs">{{ \Illuminate\Support\Str::limit($schemaError, 600) }}</p>@endif
            <p class="mt-1">Nét tự cập nhật sau khi bạn upload bản mới; nếu thấy khung này, bấm nút bên dưới để chạy lại.</p>
            <form method="POST" action="{{ route('admin.schema.update') }}" class="mt-2">
                @csrf
                <button class="rounded-md bg-[var(--ink)] text-white text-sm font-semibold px-3 py-1.5"><i class="fa-solid fa-rotate mr-1.5" aria-hidden="true"></i>Cập nhật database ngay</button>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @foreach([['Tổng số studio', $stats['total'], ''], ['Đang hoạt động', $stats['active'], 'text-emerald-700'], ['Mới trong 7 ngày', $stats['new_7d'], ''], ['Dung lượng ảnh', $stats['storage_mb'].' MB', '']] as [$label, $value, $tone])
            <div class="rounded-lg border border-[var(--line)] bg-white px-4 py-3">
                <p class="text-xs text-[var(--muted)]">{{ $label }}</p>
                <p class="font-display text-2xl font-bold tnum {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
        @forelse($studios as $s)
            @php $idle = $s->last_order_at ? (int) \Illuminate\Support\Carbon::parse($s->last_order_at)->diffInDays(now()) : null; @endphp
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 sm:px-5 py-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('admin.studios.show', $s->id) }}" class="font-semibold hover:underline">{{ $s->name }}</a>
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $s->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">{{ $s->is_active ? 'Hoạt động' : 'Đã khoá' }}</span>
                        @if($idle === null)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-stone-100 text-[var(--muted)]">Chưa có đơn</span>
                        @elseif($idle > 30)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">{{ $idle }} ngày không hoạt động</span>
                        @endif
                    </div>
                    <p class="text-sm text-[var(--muted)] mt-0.5">/dat/{{ $s->slug }} · {{ $s->owner?->name ?? '(đã xoá)' }} · {{ $s->owner?->phone }} · {{ $s->orders_count }} đơn · đăng ký {{ $s->created_at->format('d/m/Y') }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-button size="sm" variant="outline" :href="route('admin.studios.show', $s->id)">Chi tiết</x-button>
                    <form method="POST" action="{{ route('admin.studios.toggle', $s->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(($s->is_active ? 'Tạm khoá' : 'Mở lại').' studio "'.$s->name.'"?') }})">
                        @csrf @method('PUT')
                        <x-button size="sm" :variant="$s->is_active ? 'danger' : 'success'">{{ $s->is_active ? 'Tạm khoá' : 'Mở lại' }}</x-button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-center py-10 text-[var(--muted)] text-sm">{{ $search !== '' ? 'Không tìm thấy studio nào.' : 'Chưa có studio nào đăng ký.' }}</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $studios->links() }}</div>
@endsection
