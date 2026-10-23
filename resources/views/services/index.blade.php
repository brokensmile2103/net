@extends('layouts.app')
@section('title', 'Dịch vụ & bảng giá · Nét')
@section('page-title', 'Dịch vụ & bảng giá')
@section('content')
@php
    $fields = function ($s = null) use ($studio) {
        return view('services._fields', ['s' => $s, 'studio' => $studio])->render();
    };
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-4xl mx-auto" x-data="{ adding: {{ $errors->any() && ! old('_service') ? 'true' : 'false' }}, open: {{ old('_service') ? (int) old('_service') : 'null' }} }">
    <div class="flex flex-wrap items-start gap-3 mb-5">
        <p class="text-sm text-[var(--muted)] flex-1 min-w-60">Dịch vụ công khai hiện trên <a href="{{ $studio->orderFormUrl() }}" target="_blank" class="underline">trang đặt đơn</a> để khách chọn. Giá ở đây là dòng báo giá gợi ý khi tạo đơn — vẫn sửa được từng đơn.</p>
        <x-button type="button" size="sm" icon="fa-plus" @click="adding = !adding; open = null">Thêm dịch vụ</x-button>
    </div>

    <section x-show="adding" x-cloak class="rounded-lg border border-[var(--line)] bg-white p-5 mb-5">
        <h2 class="font-semibold mb-4">Dịch vụ mới</h2>
        <form method="POST" action="{{ route('services.store') }}">
            @csrf
            {!! $fields() !!}
            <div class="flex gap-2 mt-5">
                <x-button icon="fa-check">Thêm vào bảng giá</x-button>
                <x-button type="button" variant="ghost" @click="adding = false">Huỷ</x-button>
            </div>
        </form>
    </section>

    @if($services->isEmpty())
        <div class="rounded-lg border border-dashed border-[var(--line-strong)] bg-white px-6 py-12 text-center">
            <p class="font-semibold">Bảng giá đang trống.</p>
            <p class="text-sm text-[var(--muted)] mt-1">Khách vẫn đặt được đơn — bạn báo giá sau.</p>
        </div>
    @else
        <ol class="space-y-2">
            @foreach($services as $s)
                <li class="rounded-lg border border-[var(--line)] bg-white">
                    <div class="flex items-start gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $s->name }}
                                @unless($s->is_public)<span class="ml-1.5 text-[0.7rem] font-semibold rounded-full bg-stone-100 text-[var(--muted)] px-2 py-0.5 align-middle">Ẩn</span>@endunless
                            </p>
                            <p class="text-sm mt-0.5"><span class="font-semibold tnum">{{ $s->priceLabel() }}</span>
                                <span class="text-[var(--muted)]">
                                    @if($s->duration_days) · ~{{ $s->duration_days }} ngày @endif
                                    · sửa {{ $s->effectiveRevisionRounds($studio) }} lần · cọc {{ $s->effectiveDepositPercent($studio) }}%
                                    @if($s->orders_count) · {{ $s->orders_count }} đơn @endif
                                </span>
                            </p>
                            @if($s->description)<p class="text-sm text-[var(--muted)] mt-1 line-clamp-2">{{ $s->description }}</p>@endif
                            @if($n = count($s->briefQuestions()))<p class="text-xs text-[var(--accent-ink)] mt-1"><i class="fa-regular fa-rectangle-list mr-1" aria-hidden="true"></i>Mẫu brief {{ $n }} câu hỏi</p>@endif
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @unless($loop->first)
                                <form method="POST" action="{{ route('services.up', $s->id) }}">@csrf
                                    <button class="w-8 h-8 rounded hover:bg-stone-100 text-[var(--muted)]" aria-label="Đưa {{ $s->name }} lên trên"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                </form>
                            @endunless
                            <button type="button" class="w-8 h-8 rounded hover:bg-stone-100 text-[var(--muted)]" @click="open = open === {{ $s->id }} ? null : {{ $s->id }}; adding = false" :aria-expanded="open === {{ $s->id }}" aria-label="Sửa {{ $s->name }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div x-show="open === {{ $s->id }}" x-cloak class="border-t border-[var(--line)] px-4 py-4">
                        <form method="POST" action="{{ route('services.update', $s->id) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="_service" value="{{ $s->id }}">
                            {!! $fields($s) !!}
                            <div class="flex flex-wrap gap-2 mt-5">
                                <x-button icon="fa-check">Lưu</x-button>
                                <x-button type="button" variant="ghost" @click="open = null">Đóng</x-button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('services.destroy', $s->id) }}" class="mt-3" onsubmit="return confirm('Xoá dịch vụ này khỏi bảng giá? Các đơn cũ vẫn giữ nguyên.')">
                            @csrf @method('DELETE')
                            <button class="text-sm text-red-600 hover:underline"><i class="fa-solid fa-trash mr-1" aria-hidden="true"></i>Xoá khỏi bảng giá</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
@endsection
