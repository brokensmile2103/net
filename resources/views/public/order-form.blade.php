@extends('layouts.public')
@section('title', $studio->name.' · Đặt thiết kế')
@section('share_title', $studio->name.($studio->order_form_enabled ? ' · Đặt thiết kế' : ''))
@section('meta_description', $studio->shareDescription($services))
@section('canonical', route('public.order', $studio->slug))
@section('robots', $studio->order_form_enabled ? 'index, follow' : 'noindex, follow')
@section('no_header', '1')
@section('main_class', 'max-w-4xl')
@section('frame', 'dat-thiet-ke')
@section('content')
@php
    $hints = $services->mapWithKeys(fn ($s) => [$s->id => $s->brief_hint])->filter();
    $oldLinks = array_values(array_filter((array) old('links', []), fn ($l) => $l !== null)) ?: [''];
    $headline = $studio->page_headline ?: $studio->tagline;
@endphp

@if($previewing ?? false)
    <div class="mb-6 rounded-lg bg-[var(--ink)] text-white text-sm px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2" role="status">
        <span><i class="fa-solid fa-eye mr-1.5" aria-hidden="true"></i>Đang xem thử mẫu <strong>{{ config('net.page_themes.'.$theme.'.label') }}</strong> — khách vẫn thấy mẫu bạn đã lưu.</span>
        <a href="{{ route('settings.index') }}#trang-dat-don" class="underline font-semibold">Về Cài đặt để áp dụng</a>
    </div>
@endif

@include('public.heroes.'.$theme)

@if($studio->order_form_note)
    <p class="pf-note-box mb-8 rounded-lg bg-[var(--accent-soft)] text-[var(--accent-ink)] px-4 py-3 text-sm whitespace-pre-line">{{ $studio->order_form_note }}</p>
@endif

@if($services->isNotEmpty())
    <section id="bang-gia" class="mb-10 scroll-mt-6" aria-labelledby="h-price">
        <h2 id="h-price" class="pf-section-title">Bảng giá</h2>
        <ul class="pf-card pf-price rounded-lg border border-[var(--line)] bg-white divide-y divide-[var(--line)]">
            @foreach($services as $s)
                <li class="px-4 py-3.5">
                    <div class="flex items-baseline gap-3">
                        <p class="font-semibold flex-1">{{ $s->name }}</p>
                        <p class="font-semibold tnum whitespace-nowrap">{{ $s->priceLabel() }}</p>
                    </div>
                    @if($s->description)<p class="text-sm text-[var(--muted)] mt-1 whitespace-pre-line">{{ $s->description }}</p>@endif
                    <p class="text-xs text-[var(--muted)] mt-1.5">
                        @if($s->duration_days)~{{ $s->duration_days }} ngày làm · @endif
                        {{ $s->effectiveRevisionRounds($studio) }} lần sửa
                        · {{ $s->effectiveDepositPercent($studio) > 0 ? 'cọc '.$s->effectiveDepositPercent($studio).'%' : 'không cần cọc' }}
                    </p>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@unless($studio->order_form_enabled)
    <div class="pf-card rounded-lg border border-[var(--line)] bg-white px-5 py-8 text-center">
        <p class="font-display text-xl font-bold">Tạm ngừng nhận đơn mới</p>
        <p class="text-sm text-[var(--muted)] mt-2">{{ $studio->name }} đang kín lịch. Bạn có thể liên hệ trực tiếp ở cuối trang để hẹn lịch sau.</p>
    </div>
@else
    <h2 class="pf-section-title" id="h-order">Đặt thiết kế</h2>
    @if($consultSlots)
        <p class="text-sm text-[var(--muted)] -mt-2 mb-5">Chưa chắc mình cần gì? <a href="#tu-van" class="font-semibold text-[var(--accent-ink)] hover:underline">Đặt lịch gọi tư vấn trước</a>.</p>
    @endif
    <form method="POST" action="{{ route('public.order.store', $studio->slug) }}" id="dat-don" aria-labelledby="h-order" class="pf-form space-y-8 scroll-mt-6"
          x-data="{ service: @js((string) old('service_id', $services->count() === 1 ? $services->first()->id : '')), hints: @js($hints), links: @js($oldLinks) }">
        @csrf
        <x-form-guard :name="'order-'.$studio->id" />
        {{-- Form dài: gửi lỗi xong trang tải lại ở đầu, nên tóm tắt lỗi ở đây để khách không bỏ sót lỗi ở cuối form. --}}
        <x-errors />

        {{-- 1. Bạn cần gì --}}
        <fieldset class="pf-card rounded-lg border border-[var(--line)] bg-white p-5 md:p-6 space-y-5">
            <legend class="font-display text-lg font-bold tracking-tight px-1 -ml-1">1. Bạn cần thiết kế gì?</legend>
            @if($services->isNotEmpty())
                <div>
                    <p class="block text-sm font-medium text-[var(--ink-2)] mb-2" id="svc-label">Dịch vụ</p>
                    <div class="grid sm:grid-cols-2 gap-2" role="radiogroup" aria-labelledby="svc-label">
                        @foreach($services as $s)
                            <label class="flex items-start gap-2.5 rounded-lg border px-3.5 py-3 cursor-pointer transition"
                                   :class="service === '{{ $s->id }}' ? 'border-[var(--accent)] bg-[var(--accent-soft)]' : 'border-[var(--line-strong)] hover:border-[var(--ink)]'">
                                <input type="radio" name="service_id" value="{{ $s->id }}" x-model="service" class="mt-1 accent-[var(--accent)]">
                                <span class="min-w-0"><span class="block text-sm font-semibold">{{ $s->name }}</span><span class="block text-xs text-[var(--muted)] tnum">{{ $s->priceLabel() }}</span></span>
                            </label>
                        @endforeach
                        <label class="flex items-start gap-2.5 rounded-lg border px-3.5 py-3 cursor-pointer transition"
                               :class="service === '' ? 'border-[var(--accent)] bg-[var(--accent-soft)]' : 'border-[var(--line-strong)] hover:border-[var(--ink)]'">
                            <input type="radio" name="service_id" value="" x-model="service" class="mt-1 accent-[var(--accent)]">
                            <span><span class="block text-sm font-semibold">Khác / chưa rõ</span><span class="block text-xs text-[var(--muted)]">Mô tả bên dưới, mình báo giá sau</span></span>
                        </label>
                    </div>
                    @error('service_id')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div x-show="hints[service]" x-cloak class="rounded-lg bg-stone-50 border border-[var(--line)] px-4 py-3 text-sm">
                    <p class="font-semibold mb-1"><i class="fa-regular fa-lightbulb text-[var(--accent)] mr-1.5" aria-hidden="true"></i>Nên chuẩn bị</p>
                    <p class="text-[var(--ink-2)] whitespace-pre-line" x-text="hints[service]"></p>
                </div>
                @include('public._brief-questions')
            @endif

            <x-input name="title" label="Tên dự án *" :value="old('title')" required maxlength="160" placeholder="VD: Logo cho tiệm bánh Mây" />
            <x-textarea name="brief" label="Mô tả yêu cầu *" rows="6" :value="old('brief')" required maxlength="10000"
                        placeholder="Thương hiệu / sản phẩm của bạn là gì, dành cho ai? Thiết kế dùng ở đâu (bao bì, mạng xã hội, in ấn…)? Bạn muốn người xem cảm thấy thế nào?" />
            <div class="grid sm:grid-cols-2 gap-4">
                <x-input name="style" label="Phong cách mong muốn" :value="old('style')" maxlength="500" placeholder="Tối giản, cổ điển, vui tươi…" />
                <x-input name="colors" label="Màu sắc" :value="old('colors')" maxlength="255" placeholder="Xanh rêu, kem; tránh màu đỏ" />
                <x-input name="specs" label="Kích thước / định dạng" :value="old('specs')" maxlength="500" placeholder="1080×1350, A5 in offset, file AI…" />
                <x-input name="client_due_on" type="date" label="Ngày cần có" :value="old('client_due_on')" :min="now()->toDateString()" />
            </div>
            <x-textarea name="copy_text" label="Nội dung chữ trên thiết kế" rows="3" :value="old('copy_text')" maxlength="5000" placeholder="Tên thương hiệu, slogan, thông tin liên hệ… chính xác từng chữ" />
            <x-input name="budget_note" label="Ngân sách dự kiến" :value="old('budget_note')" maxlength="120" placeholder="VD: khoảng 2–3 triệu" />
        </fieldset>

        {{-- 2. Tài liệu --}}
        <fieldset class="pf-card rounded-lg border border-[var(--line)] bg-white p-5 md:p-6 space-y-4">
            <legend class="font-display text-lg font-bold tracking-tight px-1 -ml-1">2. Tài liệu & hình tham khảo</legend>
            <p class="text-sm text-[var(--muted)] -mt-1">Gom logo cũ, hình ảnh, mẫu bạn thích vào 1 thư mục Google Drive (hoặc Pinterest, Dropbox…) rồi dán link chia sẻ. Nhớ bật <span class="font-semibold text-[var(--ink-2)]">“Bất kỳ ai có đường liên kết đều xem được”</span>.</p>
            <template x-for="(link, i) in links" :key="i">
                <div class="flex gap-2">
                    <label class="sr-only" :for="'link-' + i" x-text="'Link ' + (i + 1)"></label>
                    <input type="url" :id="'link-' + i" name="links[]" x-model="links[i]" maxlength="2000" placeholder="https://drive.google.com/…" inputmode="url"
                           class="flex-1 min-w-0 rounded-lg border border-[var(--line-strong)] bg-white py-2.5 px-3.5 text-[0.95rem] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)]">
                    <button type="button" class="w-10 rounded-lg text-[var(--muted)] hover:bg-stone-100" x-show="links.length > 1" @click="links.splice(i, 1)" :aria-label="'Bỏ link ' + (i + 1)"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
            </template>
            @foreach($errors->get('links.*') as $msgs)<p class="text-xs text-red-600">{{ $msgs[0] }}</p>@endforeach
            <button type="button" class="text-sm font-semibold text-[var(--accent-ink)]" x-show="links.length < 5" @click="links.push('')"><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Thêm link</button>
        </fieldset>

        {{-- 3. Liên hệ --}}
        <fieldset class="pf-card rounded-lg border border-[var(--line)] bg-white p-5 md:p-6 space-y-4">
            <legend class="font-display text-lg font-bold tracking-tight px-1 -ml-1">3. Thông tin liên hệ</legend>
            <p class="text-sm text-[var(--muted)] -mt-1">Để lại <span class="font-semibold text-[var(--ink-2)]">ít nhất 1</span> cách liên hệ: số điện thoại, email hoặc Facebook.</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2"><x-input name="name" label="Tên của bạn *" :value="old('name')" required maxlength="100" autocomplete="name" /></div>
                <x-input name="phone" label="Số điện thoại / Zalo" :value="old('phone')" inputmode="tel" autocomplete="tel" maxlength="20" />
                <x-input name="email" type="email" label="Email" :value="old('email')" autocomplete="email" maxlength="255" />
                <div class="sm:col-span-2"><x-input name="facebook" label="Facebook" :value="old('facebook')" maxlength="255"
                    placeholder="facebook.com/ten.cua.ban hoặc tên Facebook" hint="Dán link trang cá nhân để mình nhắn Messenger cho bạn nhanh hơn." /></div>
            </div>
        </fieldset>

        @if($studio->policy)
            <section class="pf-card rounded-lg border border-[var(--line)] bg-white p-5 md:p-6" aria-labelledby="h-policy">
                <h2 id="h-policy" class="font-display text-lg font-bold tracking-tight">Quy định làm việc</h2>
                <div class="mt-3 text-sm text-[var(--ink-2)] leading-relaxed whitespace-pre-line max-h-64 overflow-y-auto">{{ $studio->policy }}</div>
                <label class="mt-4 flex items-start gap-2.5 text-sm">
                    <input type="checkbox" name="agree" value="1" class="mt-0.5 accent-[var(--accent)]" @checked(old('agree')) required>
                    <span>Tôi đã đọc và đồng ý với quy định trên.</span>
                </label>
                @error('agree')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </section>
        @endif

        <div>
            <x-button size="lg" icon="fa-paper-plane" class="pf-submit w-full sm:w-auto">Gửi yêu cầu</x-button>
            <p class="text-xs text-[var(--muted)] mt-3">Sau khi gửi, bạn nhận được 1 link riêng để theo dõi tiến độ, xem phương án và góp ý — hãy lưu lại link đó.</p>
        </div>
    </form>
@endunless

@if($consultSlots !== null)
    @include('public._consult')
@endif
@endsection
