@extends('layouts.admin')
@section('title', 'Tài khoản · Quản trị Nét')
@section('content')
    <div x-data="{ resettingFor: null }">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <h1 class="font-display text-2xl font-bold tracking-tight flex-1">Tài khoản</h1>
            <form method="GET">
                <label for="user-search" class="sr-only">Tìm tài khoản</label>
                <input type="search" name="q" id="user-search" value="{{ request('q') }}" placeholder="Tên, SĐT…" autocomplete="off"
                       class="rounded-lg border border-[var(--line-strong)] bg-white py-2 px-3.5 text-sm w-full sm:w-64">
            </form>
        </div>

        <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
            @forelse($users as $u)
                <div class="px-4 sm:px-5 py-4">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold">{{ $u->name }}</span>
                                @if($u->isPlatformAdmin())
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--ink)] text-white font-semibold">Quản trị</span>
                                @endif
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $u->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">{{ $u->is_active ? 'Hoạt động' : 'Đã khoá' }}</span>
                            </div>
                            <p class="text-sm text-[var(--muted)] mt-0.5">
                                <span class="tnum">{{ $u->phone }}</span>@if($u->email) · {{ $u->email }}@endif
                                @if($u->studio) · Studio: <a href="{{ route('admin.studios.show', $u->studio->id) }}" class="underline">{{ $u->studio->name }}</a>@endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-button type="button" size="sm" variant="outline" icon="fa-key" @click="resettingFor = resettingFor === {{ $u->id }} ? null : {{ $u->id }}">Đặt mật khẩu</x-button>
                            @unless($u->id === auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(($u->is_active ? 'Khoá' : 'Mở lại').' tài khoản '.$u->name.'?') }})">
                                    @csrf @method('PUT')
                                    <x-button size="sm" :variant="$u->is_active ? 'danger' : 'success'">{{ $u->is_active ? 'Khoá' : 'Mở lại' }}</x-button>
                                </form>
                            @endunless
                        </div>
                    </div>
                    <form x-show="resettingFor === {{ $u->id }}" x-cloak method="POST" action="{{ route('admin.users.password', $u->id) }}" class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-[var(--line)]">
                        @csrf @method('PUT')
                        <label for="reset-pw-{{ $u->id }}" class="sr-only">Mật khẩu mới</label>
                        <input type="password" name="password" id="reset-pw-{{ $u->id }}" placeholder="Mật khẩu mới (tối thiểu 8 ký tự)" required minlength="8" autocomplete="new-password"
                               class="flex-1 min-w-[10rem] rounded-lg border border-[var(--line-strong)] py-2 px-3 text-sm">
                        <x-button size="sm" variant="ink">Lưu</x-button>
                    </form>
                </div>
            @empty
                <p class="text-center py-10 text-[var(--muted)] text-sm">Không tìm thấy tài khoản nào.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
@endsection
