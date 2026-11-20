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
    @if($canPriceImage)
        <section class="mt-8 rounded-lg border border-[var(--line)] bg-white p-5" aria-labelledby="h-price-image"
                 x-data="netPriceImage(@js(route('services.price-image')), @js('bang-gia-'.$studio->slug.'.png'))">
            <h2 id="h-price-image" class="font-semibold">Ảnh bảng giá</h2>
            <p class="text-sm text-[var(--muted)] mt-1">Tạo ảnh từ các dịch vụ đang công khai, vẽ theo giao diện <strong>{{ $priceImageTheme }}</strong> và màu nhấn của studio — gửi khách qua Zalo, Messenger, Facebook. Đổi giao diện ở Cài đặt → Trang đặt đơn.</p>
            <div class="flex flex-wrap items-center gap-2 mt-4">
                <x-button type="button" size="sm" variant="outline" icon="fa-download" x-on:click="download()" x-bind:disabled="busy !== ''">Tải ảnh về</x-button>
                <x-button type="button" size="sm" variant="outline" icon="fa-copy" x-on:click="copy()" x-bind:disabled="busy !== ''">Sao chép ảnh</x-button>
            </div>
            <p class="text-sm mt-3" role="status" aria-live="polite" x-cloak x-show="msg !== ''" x-text="msg" :class="ok ? 'text-[var(--muted)]' : 'text-red-600'"></p>
        </section>
        <script>
            /* Tải / sao chép ảnh bảng giá. Không cần build lại JS: chạy trước khi Alpine khởi động. */
            window.netPriceImage = function (url, filename) {
                return {
                    busy: '', msg: '', ok: true, timer: null,
                    say(text, ok) {
                        this.msg = text; this.ok = ok;
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => { this.msg = ''; }, 6000);
                    },
                    async fetchPng(download) {
                        const res = await fetch(url + (download ? '?tai=1' : ''), { credentials: 'same-origin', cache: 'no-store' });
                        if (!res.ok) {
                            let why = '';
                            try { why = (await res.text()).slice(0, 160); } catch (e) {}
                            throw new Error(res.status === 429 ? 'Bấm nhanh quá, đợi một chút rồi thử lại.' : (res.status === 404 || res.status === 503) && why ? why : 'Không tạo được ảnh (lỗi ' + res.status + ').');
                        }
                        const blob = await res.blob();
                        return blob.type === 'image/png' ? blob : new Blob([blob], { type: 'image/png' });
                    },
                    async download() {
                        if (this.busy) return;
                        this.busy = 'download'; this.msg = '';
                        try {
                            const blob = await this.fetchPng(true);
                            const href = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = href; a.download = filename;
                            document.body.appendChild(a); a.click(); a.remove();
                            setTimeout(() => URL.revokeObjectURL(href), 4000);
                            this.say('Đã tải ảnh bảng giá về máy.', true);
                        } catch (e) {
                            this.say(e.message || 'Không tải được ảnh.', false);
                        } finally { this.busy = ''; }
                    },
                    async copy() {
                        if (this.busy) return;
                        if (!(navigator.clipboard && window.ClipboardItem && window.isSecureContext)) {
                            this.say('Trình duyệt này chưa cho sao chép ảnh (cần HTTPS và trình duyệt mới). Hãy bấm "Tải ảnh về".', false);
                            return;
                        }
                        this.busy = 'copy'; this.msg = '';
                        try {
                            // Đưa Promise cho ClipboardItem ngay trong lượt bấm — Safari bắt buộc làm vậy.
                            await navigator.clipboard.write([new ClipboardItem({ 'image/png': this.fetchPng(false) })]);
                            this.say('Đã sao chép ảnh — dán vào Zalo, Messenger, Facebook bằng Ctrl + V (⌘ + V).', true);
                        } catch (e) {
                            this.say(e && e.name === 'NotAllowedError' ? 'Trình duyệt không cho sao chép. Hãy bấm "Tải ảnh về".' : (e.message || 'Không sao chép được ảnh.'), false);
                        } finally { this.busy = ''; }
                    },
                };
            };
        </script>
    @endif
</div>
@endsection
