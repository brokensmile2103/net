@extends('layouts.app')
@section('title', 'Khách hàng · Nét')
@section('page-title', 'Khách hàng')
@section('content')
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto" x-data="{ adding: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex flex-wrap gap-2 mb-5">
        <form method="GET" class="flex gap-2 flex-1 min-w-0">
            <label for="q" class="sr-only">Tìm khách</label>
            <div class="relative flex-1 max-w-md">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm" aria-hidden="true"></i>
                <input id="q" data-search-focus name="q" value="{{ $q }}" placeholder="Tên, SĐT, email, Facebook, công ty" class="w-full rounded-lg border border-[var(--line-strong)] bg-white pl-9 pr-3 py-2 text-sm">
            </div>
            <x-button size="sm" variant="ink">Tìm</x-button>
        </form>
        <x-button type="button" size="sm" icon="fa-user-plus" @click="adding = !adding" ::aria-expanded="adding">Thêm khách</x-button>
    </div>

    <section x-show="adding" x-cloak class="rounded-lg border border-[var(--line)] bg-white p-5 mb-5">
        <h2 class="font-semibold mb-4">Thêm khách</h2>
        <form method="POST" action="{{ route('clients.store') }}" class="grid sm:grid-cols-2 gap-4">
            @csrf
            <x-input name="name" label="Tên khách *" :value="old('name')" required maxlength="100" />
            <x-input name="company" label="Công ty / thương hiệu" :value="old('company')" maxlength="120" />
            <p class="sm:col-span-2 text-xs text-[var(--muted)] -mb-2">Cần ít nhất 1 trong 3: số điện thoại, email hoặc Facebook.</p>
            <x-input name="phone" label="Số điện thoại / Zalo" :value="old('phone')" inputmode="tel" maxlength="20" />
            <x-input name="email" type="email" label="Email" :value="old('email')" maxlength="255" />
            <div class="sm:col-span-2"><x-input name="facebook" label="Facebook" :value="old('facebook')" maxlength="255" placeholder="facebook.com/ten.khach hoặc tên Facebook" /></div>
            <div class="sm:col-span-2"><x-input name="contact_url" label="Link liên hệ khác (Instagram, Zalo OA…)" :value="old('contact_url')" placeholder="https://" /></div>
            <div class="sm:col-span-2"><x-textarea name="note" label="Ghi chú riêng" rows="2" :value="old('note')" placeholder="Khách chỉ duyệt vào buổi tối, thích tông lạnh…" /></div>
            <div class="sm:col-span-2 flex gap-2">
                <x-button icon="fa-check">Lưu khách</x-button>
                <x-button type="button" variant="ghost" @click="adding = false">Huỷ</x-button>
            </div>
        </form>
    </section>

    @if($clients->isEmpty())
        <div class="rounded-lg border border-dashed border-[var(--line-strong)] bg-white px-6 py-12 text-center">
            <p class="font-semibold">{{ $q !== '' ? 'Không tìm thấy khách nào.' : 'Chưa có khách nào.' }}</p>
            <p class="text-sm text-[var(--muted)] mt-1">Khách tự đặt qua link sẽ được thêm vào đây — nhận ra khách cũ theo số điện thoại, email hoặc Facebook.</p>
        </div>
    @else
        <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
            @foreach($clients as $c)
                <a href="{{ route('clients.show', $c->id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
                    <span class="w-10 h-10 rounded-full bg-stone-100 text-[var(--ink-2)] flex items-center justify-center text-sm font-semibold shrink-0" aria-hidden="true">{{ $c->initials() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold truncate">{{ $c->name }}@if($c->company)<span class="font-normal text-[var(--muted)]"> · {{ $c->company }}</span>@endif</span>
                        <span class="block text-xs text-[var(--muted)] truncate mt-0.5">{{ $c->contactLine() }}</span>
                    </span>
                    <span class="text-right text-xs text-[var(--muted)] shrink-0">
                        <span class="block"><span class="tnum font-semibold text-[var(--ink)]">{{ $c->orders_count }}</span> đơn</span>
                        @if((float) $c->revenue > 0)<span class="block tnum mt-0.5">{{ money_compact($c->revenue) }}</span>@endif
                    </span>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
</div>
@endsection
