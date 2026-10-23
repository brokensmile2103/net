@extends('layouts.app')
@section('title', $client->name.' · Nét')
@section('page-title', 'Khách hàng')
@section('actions')
    <x-button size="sm" icon="fa-plus" :href="route('orders.create', ['khach' => $client->id])">Đơn mới cho khách</x-button>
@endsection
@section('content')
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto" x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }} }">
    <a href="{{ route('clients.index') }}" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Tất cả khách</a>

    <div class="mt-3 flex flex-wrap items-start gap-4">
        <span class="w-14 h-14 rounded-full bg-[var(--accent-soft)] text-[var(--accent-ink)] flex items-center justify-center text-lg font-bold shrink-0" aria-hidden="true">{{ $client->initials() }}</span>
        <div class="min-w-0 flex-1">
            <h2 class="font-display text-2xl font-bold tracking-tight">{{ $client->name }}</h2>
            @if($client->company)<p class="text-[var(--muted)]">{{ $client->company }}</p>@endif
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                @if($client->phone)<a href="{{ $client->telLink() }}" class="hover:underline"><i class="fa-solid fa-phone text-[var(--muted)] mr-1.5" aria-hidden="true"></i>{{ $client->phone }}</a>
                    <a href="https://zalo.me/{{ preg_replace('/\D/', '', $client->phone) }}" target="_blank" rel="noopener" class="hover:underline"><i class="fa-solid fa-comment text-[var(--muted)] mr-1.5" aria-hidden="true"></i>Zalo</a>@endif
                @if($client->email)<a href="mailto:{{ $client->email }}" class="hover:underline"><i class="fa-solid fa-envelope text-[var(--muted)] mr-1.5" aria-hidden="true"></i>{{ $client->email }}</a>
                    @php $mailCheck = $client->emailCheck(); @endphp
                    @unless($mailCheck['ok'])<span class="text-xs text-amber-800 bg-amber-50 rounded px-1.5 py-0.5" title="Nét không tự gửi email nhắc tới địa chỉ này"><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ $mailCheck['reason'] }}@if($mailCheck['suggestion']) — có phải {{ $mailCheck['suggestion'] }}?@endif</span>@endunless
                @endif
                @if($client->facebook)
                    @if($client->facebookUrl())
                        <a href="{{ $client->facebookUrl() }}" target="_blank" rel="noopener nofollow" class="hover:underline"><i class="fa-brands fa-facebook text-[var(--muted)] mr-1.5" aria-hidden="true"></i>{{ $client->facebookLabel() }}</a>
                    @else
                        <span title="Tên Facebook khách ghi"><i class="fa-brands fa-facebook text-[var(--muted)] mr-1.5" aria-hidden="true"></i>{{ $client->facebookLabel() }}</span>
                    @endif
                    @if($client->messengerUrl())<a href="{{ $client->messengerUrl() }}" target="_blank" rel="noopener nofollow" class="hover:underline"><i class="fa-brands fa-facebook-messenger text-[var(--muted)] mr-1.5" aria-hidden="true"></i>Messenger</a>@endif
                @endif
                @if($client->contact_url)<a href="{{ $client->contact_url }}" target="_blank" rel="noopener nofollow" class="hover:underline"><i class="fa-solid fa-link text-[var(--muted)] mr-1.5" aria-hidden="true"></i>{{ link_host_label($client->contact_url) }}</a>@endif
            </div>
        </div>
        <x-button type="button" size="sm" variant="outline" icon="fa-pen" @click="editing = !editing">Sửa</x-button>
    </div>

    @if($client->note)
        <p class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm whitespace-pre-line"><i class="fa-solid fa-note-sticky text-amber-600 mr-1.5" aria-hidden="true"></i>{{ $client->note }}</p>
    @endif

    <section x-show="editing" x-cloak class="rounded-lg border border-[var(--line)] bg-white p-5 mt-5">
        <form method="POST" action="{{ route('clients.update', $client->id) }}" class="grid sm:grid-cols-2 gap-4">
            @csrf @method('PUT')
            <x-input name="name" label="Tên khách *" :value="old('name', $client->name)" required maxlength="100" />
            <x-input name="company" label="Công ty / thương hiệu" :value="old('company', $client->company)" maxlength="120" />
            <p class="sm:col-span-2 text-xs text-[var(--muted)] -mb-2">Cần ít nhất 1 trong 3: số điện thoại, email hoặc Facebook.</p>
            <x-input name="phone" label="Số điện thoại / Zalo" :value="old('phone', $client->phone)" inputmode="tel" maxlength="20" />
            <x-input name="email" type="email" label="Email" :value="old('email', $client->email)" maxlength="255" />
            <div class="sm:col-span-2"><x-input name="facebook" label="Facebook" :value="old('facebook', $client->facebook)" maxlength="255" placeholder="facebook.com/ten.khach hoặc tên Facebook" /></div>
            <div class="sm:col-span-2"><x-input name="contact_url" label="Link liên hệ khác (Instagram, Zalo OA…)" :value="old('contact_url', $client->contact_url)" placeholder="https://" /></div>
            <div class="sm:col-span-2"><x-textarea name="note" label="Ghi chú riêng (khách không thấy)" rows="3" :value="old('note', $client->note)" /></div>
            <div class="sm:col-span-2 flex flex-wrap gap-2">
                <x-button icon="fa-check">Lưu</x-button>
                <x-button type="button" variant="ghost" @click="editing = false">Huỷ</x-button>
            </div>
        </form>
        @if($orders->isEmpty())
            <form method="POST" action="{{ route('clients.destroy', $client->id) }}" class="mt-4 pt-4 border-t border-[var(--line)]" onsubmit="return confirm('Xoá khách {{ addslashes($client->name) }}?')">
                @csrf @method('DELETE')
                <x-button size="sm" variant="danger" icon="fa-trash">Xoá khách</x-button>
            </form>
        @endif
    </section>

    <div class="grid grid-cols-3 gap-3 mt-6">
        <div class="rounded-lg border border-[var(--line)] bg-white px-4 py-3"><p class="text-xs text-[var(--muted)]">Số đơn</p><p class="font-display text-2xl font-bold tnum">{{ $stats['count'] }}</p></div>
        <div class="rounded-lg border border-[var(--line)] bg-white px-4 py-3"><p class="text-xs text-[var(--muted)]">Đã trả</p><p class="font-display text-2xl font-bold tnum">{{ money_compact($stats['paid']) }}</p></div>
        <div class="rounded-lg border border-[var(--line)] bg-white px-4 py-3"><p class="text-xs text-[var(--muted)]">Còn nợ</p><p class="font-display text-2xl font-bold tnum {{ $stats['due'] > 0 ? 'text-red-600' : '' }}">{{ money_compact($stats['due']) }}</p></div>
    </div>

    <h3 class="text-sm font-semibold mt-7 mb-2">Đơn của khách</h3>
    @if($orders->isEmpty())
        <p class="rounded-lg border border-dashed border-[var(--line-strong)] px-4 py-8 text-sm text-[var(--muted)] text-center">Chưa có đơn nào.</p>
    @else
        <div class="rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
            @foreach($orders as $o)
                @include('orders._row', ['order' => $o, 'meta' => $o->created_at->format('d/m/Y')])
            @endforeach
        </div>
    @endif
</div>
@endsection
