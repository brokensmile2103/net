@extends('layouts.app')
@section('title', 'Cài đặt · Nét')
@section('page-title', 'Cài đặt')
@section('content')
@php
    $sections = ['studio' => 'Hồ sơ studio', 'nhan-don' => 'Nhận đơn & quy định', 'thanh-toan' => 'Nhận chuyển khoản', 'nhac-khach' => 'Nhắc khách tự động', 'tu-van' => 'Hẹn gọi tư vấn', 'trang-dat-don' => 'Trang đặt đơn', 'tro-ly-ai' => 'Trợ lý AI', 'du-lieu' => 'Dữ liệu'];
    $box = 'rounded-lg border border-[var(--line)] bg-white p-5 md:p-6 scroll-mt-20';
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto md:grid md:grid-cols-[11rem_1fr] md:gap-8">
    <nav class="hidden md:block" aria-label="Mục cài đặt">
        <ul class="sticky top-6 space-y-0.5 text-sm">
            @foreach($sections as $id => $label)
                <li><a href="#{{ $id }}" class="block rounded-md px-3 py-1.5 text-[var(--ink-2)] hover:bg-white">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="space-y-6 min-w-0">
        <x-errors />

        {{-- Hồ sơ studio --}}
        <section id="studio" class="{{ $box }}" aria-labelledby="h-studio">
            <h2 id="h-studio" class="font-display text-lg font-bold tracking-tight">Hồ sơ studio</h2>
            <p class="text-sm text-[var(--muted)] mt-0.5">Khách thấy ở trang đặt đơn và trang theo dõi tiến độ.</p>

            <div class="flex items-center gap-4 mt-5">
                @if($studio->avatarUrl())
                    <img src="{{ $studio->avatarUrl() }}" alt="Ảnh đại diện {{ $studio->name }}" class="w-16 h-16 rounded-full object-cover ring-1 ring-[var(--line)]">
                @else
                    <span class="w-16 h-16 rounded-full bg-[var(--accent)] text-[var(--on-accent)] flex items-center justify-center font-display text-xl font-bold" aria-hidden="true">{{ $studio->initials() }}</span>
                @endif
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" x-data data-paste-zone="Ảnh đại diện">
                        @csrf
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md font-semibold bg-white border border-[var(--line-strong)] hover:border-[var(--ink)] cursor-pointer">
                            <i class="fa-solid fa-image" aria-hidden="true"></i>{{ $studio->avatar_path ? 'Đổi ảnh' : 'Tải ảnh đại diện' }}
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="$el.form.requestSubmit()">
                        </label>
                    </form>
                    @if($studio->avatar_path)
                        <form method="POST" action="{{ route('settings.avatar') }}">@csrf<input type="hidden" name="remove" value="1">
                            <x-button size="sm" variant="ghost">Gỡ ảnh</x-button>
                        </form>
                    @endif
                </div>
            </div>
            <p class="text-xs text-[var(--muted)] mt-2">Logo hoặc ảnh chân dung — tự cắt vuông và nén nhỏ. <x-paste-hint prefix="Trỏ chuột vào nút rồi bấm" /></p>

            <form method="POST" action="{{ route('settings.profile') }}" class="grid sm:grid-cols-2 gap-4 mt-5"
                  x-data="{ slug: @js(old('slug', $studio->slug)), base: @js(url('/dat').'/') }">
                @csrf @method('PUT')
                <x-input name="name" label="Tên studio / tên bạn *" :value="old('name', $studio->name)" required maxlength="100" />
                <div>
                    <x-input name="slug" label="Địa chỉ trang đặt đơn *" x-model="slug" x-on:change="slug = window.slugify(slug)" required maxlength="40" pattern="[a-z0-9-]{3,40}" />
                    <p class="mt-1 text-xs text-[var(--muted)] break-all"><span x-text="base"></span><span class="font-semibold text-[var(--ink)]" x-text="slug || '…'"></span></p>
                </div>
                <div class="sm:col-span-2"><x-input name="tagline" label="Một dòng giới thiệu" :value="old('tagline', $studio->tagline)" maxlength="160" placeholder="Logo & nhận diện cho quán nhỏ, thương hiệu mới" /></div>
                <div class="sm:col-span-2"><x-textarea name="bio" label="Giới thiệu" rows="3" :value="old('bio', $studio->bio)" placeholder="Bạn làm gì, cho ai, phong cách ra sao…" /></div>
                <x-input name="phone" label="Số điện thoại / Zalo" :value="old('phone', $studio->phone)" inputmode="tel" />
                <x-input name="email" type="email" label="Email liên hệ" :value="old('email', $studio->email)" />
                <div class="sm:col-span-2"><x-input name="contact_url" label="Portfolio / trang cá nhân" :value="old('contact_url', $studio->contact_url)" placeholder="https://behance.net/…" /></div>
                <div class="sm:col-span-2"><x-button icon="fa-check">Lưu hồ sơ</x-button></div>
            </form>
        </section>

        {{-- Nhận đơn --}}
        <section id="nhan-don" class="{{ $box }}" aria-labelledby="h-order">
            <h2 id="h-order" class="font-display text-lg font-bold tracking-tight">Nhận đơn & quy định</h2>
            <form method="POST" action="{{ route('settings.order-form') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="order_form_enabled" value="1" class="mt-1 rounded accent-[var(--accent)]" @checked(old('order_form_enabled', $studio->order_form_enabled))>
                    <span><span class="font-semibold text-sm">Mở trang đặt đơn</span><span class="block text-sm text-[var(--muted)]">Tắt khi kín lịch — khách vẫn xem được trang, nhưng không gửi đơn mới.</span></span>
                </label>
                <x-textarea name="order_form_note" label="Lời nhắn trên trang đặt đơn" rows="2" :value="old('order_form_note', $studio->order_form_note)" placeholder="Lịch tháng 11 còn 3 slot. Mình phản hồi trong vòng 24 giờ." />
                <x-textarea name="policy" label="Quy định làm việc" rows="5" :value="old('policy', $studio->policy)" hint="Nếu có, khách phải tick đồng ý trước khi gửi đơn." placeholder="- Cọc 50% trước khi bắt đầu, không hoàn cọc khi đã gửi bản phác thảo.&#10;- Mỗi đơn gồm 2 lần sửa, từ lần thứ 3 tính 200.000đ/lần.&#10;- File gốc gửi sau khi thanh toán đủ." />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input name="default_deposit_percent" type="number" min="0" max="100" label="Cọc mặc định (%)" :value="old('default_deposit_percent', $studio->default_deposit_percent)" required hint="Đặt 0 nếu không lấy cọc — khách trả một lần sau khi duyệt." />
                    <x-input name="default_revision_rounds" type="number" min="0" max="20" label="Số lần sửa mặc định" :value="old('default_revision_rounds', $studio->default_revision_rounds)" required />
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="lock_delivery_until_paid" value="1" class="mt-1 rounded accent-[var(--accent)]" @checked(old('lock_delivery_until_paid', $studio->lock_delivery_until_paid))>
                    <span><span class="font-semibold text-sm">Khoá link file gốc tới khi khách trả đủ</span><span class="block text-sm text-[var(--muted)]">Áp dụng cho đơn mới; từng đơn vẫn bật/tắt được riêng.</span></span>
                </label>
                <x-button icon="fa-check">Lưu</x-button>
            </form>
        </section>

        {{-- Thanh toán --}}
        <section id="thanh-toan" class="{{ $box }}" aria-labelledby="h-pay">
            <h2 id="h-pay" class="font-display text-lg font-bold tracking-tight">Nhận chuyển khoản</h2>
            <p class="text-sm text-[var(--muted)] mt-0.5">Trang theo dõi sẽ hiện mã VietQR đúng số tiền cần trả và nội dung chuyển khoản theo mã đơn.</p>
            <form method="POST" action="{{ route('settings.payment') }}" class="grid sm:grid-cols-3 gap-4 mt-5">
                @csrf @method('PUT')
                <x-input name="bank_code" label="Ngân hàng" :value="old('bank_code', $studio->bank_code)" placeholder="VCB, MB, TCB…" maxlength="20" />
                <x-input name="bank_account" label="Số tài khoản" :value="old('bank_account', $studio->bank_account)" inputmode="numeric" maxlength="40" />
                <x-input name="bank_holder" label="Chủ tài khoản" :value="old('bank_holder', $studio->bank_holder)" placeholder="NGUYEN VAN A" maxlength="100" />
                <div class="sm:col-span-3">
                    <x-input name="bank_memo" label="Nội dung chuyển khoản" :value="old('bank_memo', $studio->bankMemoTemplate())" placeholder="NET {code}" maxlength="50" />
                    <p class="text-xs text-[var(--muted)] mt-1">Điền sẵn vào mã VietQR. Dùng <code class="font-mono">{code}</code> cho mã đơn (VD: <code class="font-mono">NET {code}</code> → NET N0001). Để trống nếu muốn khách tự nhập hoặc ngân hàng tự điền.</p>
                </div>
                <div class="sm:col-span-3 flex flex-wrap items-center gap-4">
                    <x-button icon="fa-check">Lưu</x-button>
                    @if($studio->hasVietQr())
                        <span class="text-sm text-emerald-700"><i class="fa-solid fa-qrcode mr-1" aria-hidden="true"></i>Đã bật VietQR</span>
                    @endif
                </div>
            </form>
            @if($studio->hasVietQr())
                <div class="mt-5 flex items-center gap-4 rounded-lg bg-stone-50 p-4">
                    <img src="{{ vietqr_url($studio->bank_code, $studio->bank_account, 10000, \App\Models\Studio::renderMemo($studio->bankMemoTemplate(), ['code' => 'N0001']), $studio->bank_holder) }}" alt="Mã QR thử 10.000đ" class="w-28 h-28 rounded bg-white" loading="lazy">
                    <p class="text-sm text-[var(--muted)]">Mã thử 10.000đ — quét bằng app ngân hàng để chắc thông tin đúng (không cần chuyển).</p>
                </div>
            @endif
        </section>

        @include('settings._reminders')
        @include('settings._consult')

        {{-- Trang đặt đơn: mẫu, màu, câu chào, ảnh bìa, tác phẩm --}}
        @php
            $presets = config('net.accent_colors');
            $curAccent = old('accent_color', $studio->accent_color);
            $curCustom = old('accent_custom', $studio->accent_custom ?: '#ff5a1f');
            $maxWorks = (int) config('net.portfolio.max_items', 9);
        @endphp
        <section id="trang-dat-don" class="{{ $box }}" aria-labelledby="h-page">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="h-page" class="font-display text-lg font-bold tracking-tight">Trang đặt đơn</h2>
                    <p class="text-sm text-[var(--muted)] mt-0.5">Gương mặt của bạn với khách: chọn mẫu, màu thương hiệu, câu chào và tác phẩm tiêu biểu. Áp dụng cho cả trang theo dõi đơn.</p>
                </div>
                <x-button variant="outline" size="sm" icon="fa-arrow-up-right-from-square" :href="route('public.order', $studio->slug)" target="_blank" rel="noopener">Mở trang</x-button>
            </div>

            <form method="POST" action="{{ route('settings.appearance') }}" class="mt-5 space-y-6"
                  x-data="{ theme: @js(old('page_theme', $studio->pageTheme())), c: @js($curAccent), hex: @js($curCustom),
                            presets: @js(collect($presets)->map(fn ($p) => $p['hex'])),
                            apply() {
                                const root = document.documentElement;
                                ['--accent', '--accent-hover', '--accent-ink', '--accent-soft', '--accent-line', '--on-accent'].forEach(v => root.style.removeProperty(v));
                                if (this.c === 'custom' && /^#[0-9a-f]{6}$/i.test(this.hex)) { root.dataset.accent = 'custom'; root.style.setProperty('--accent', this.hex); root.style.setProperty('--accent-hover', this.hex); }
                                else if (this.c !== 'custom') { root.dataset.accent = this.c; }
                            } }">
                @csrf @method('PUT')

                {{-- Mẫu --}}
                <fieldset>
                    <legend class="text-sm font-semibold mb-2">Mẫu giao diện</legend>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach(config('net.page_themes') as $key => $t)
                            <div class="rounded-lg border-2 p-2 transition" :class="theme === @js($key) ? 'border-[var(--accent)] bg-[var(--accent-soft)]/40' : 'border-[var(--line)] hover:border-[var(--line-strong)]'">
                                <label class="block cursor-pointer">
                                    <input type="radio" name="page_theme" value="{{ $key }}" x-model="theme" class="sr-only peer">
                                    @include('settings._theme-thumb', ['key' => $key])
                                    <span class="flex items-center gap-1.5 mt-2 text-sm font-semibold peer-focus-visible:underline">
                                        <i class="fa-solid fa-circle-check text-[var(--accent)]" x-show="theme === @js($key)" x-cloak aria-hidden="true"></i>{{ $t['label'] }}
                                    </span>
                                    <span class="block text-xs text-[var(--muted)] mt-0.5 leading-snug">{{ $t['desc'] }}</span>
                                </label>
                                <a href="{{ route('public.order', ['slug' => $studio->slug, 'xem-thu' => $key]) }}" target="_blank" rel="noopener" class="inline-block mt-1.5 text-xs font-semibold text-[var(--accent-ink)] hover:underline">Xem thử<span class="sr-only"> mẫu {{ $t['label'] }}</span></a>
                            </div>
                        @endforeach
                    </div>
                    @error('page_theme')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                {{-- Màu nhấn --}}
                <fieldset>
                    <legend class="text-sm font-semibold mb-1">Màu nhấn</legend>
                    <p class="text-xs text-[var(--muted)] mb-3">Dùng cho nút, điểm nhấn trên trang khách và khu làm việc của bạn. Màu riêng: Nét tự chọn chữ trắng hay đen trên nút để luôn đọc rõ.</p>
                    <div class="flex flex-wrap items-end gap-3">
                        @foreach($presets as $key => $color)
                            <label class="cursor-pointer text-center">
                                <input type="radio" name="accent_color" value="{{ $key }}" class="sr-only peer" x-model="c" @change="apply()">
                                <span class="block w-11 h-11 rounded-full ring-offset-2 peer-checked:ring-2 peer-focus-visible:ring-2 ring-[var(--ink)]" style="background: {{ $color['hex'] }}"></span>
                                <span class="block text-xs mt-1.5 text-[var(--ink-2)]">{{ $color['label'] }}</span>
                            </label>
                        @endforeach
                        <label class="cursor-pointer text-center">
                            <input type="radio" name="accent_color" value="custom" class="sr-only peer" x-model="c" @change="apply()">
                            <span class="block w-11 h-11 rounded-full ring-offset-2 peer-checked:ring-2 peer-focus-visible:ring-2 ring-[var(--ink)]"
                                  :style="'background:' + (/^#[0-9a-f]{6}$/i.test(hex) ? hex : '#ccc')"
                                  style="background: conic-gradient(#f43f5e, #f59e0b, #22c55e, #06b6d4, #6366f1, #f43f5e)"></span>
                            <span class="block text-xs mt-1.5 text-[var(--ink-2)]">Màu riêng</span>
                        </label>
                        <div class="flex items-center gap-2" x-show="c === 'custom'" x-cloak>
                            <label for="accent-picker" class="sr-only">Chọn màu</label>
                            <input id="accent-picker" type="color" :value="/^#[0-9a-f]{6}$/i.test(hex) ? hex : '#ff5a1f'" @input="hex = $event.target.value; apply()" class="w-11 h-11 rounded-md border border-[var(--line-strong)] bg-white p-1 cursor-pointer">
                            <label for="accent_custom" class="sr-only">Mã màu HEX</label>
                            <input id="accent_custom" name="accent_custom" x-model="hex" @input="apply()" maxlength="7" placeholder="#ff5a1f" autocomplete="off"
                                   class="w-28 rounded-lg border {{ $errors->has('accent_custom') ? 'border-red-400' : 'border-[var(--line-strong)]' }} bg-white px-3 py-2.5 text-sm font-mono tnum">
                        </div>
                    </div>
                    @error('accent_color')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('accent_custom')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <x-input name="page_headline" label="Câu chào lớn ở đầu trang" :value="old('page_headline', $studio->page_headline)" maxlength="160"
                         placeholder="VD: Thương hiệu nhỏ, câu chuyện lớn — mình vẽ cho bạn một gương mặt dễ nhớ."
                         hint="Để trống thì dùng “Một dòng giới thiệu” ở Hồ sơ studio." />

                <x-button icon="fa-check">Lưu giao diện</x-button>
            </form>

            {{-- Ảnh bìa --}}
            <div class="mt-8 pt-6 border-t border-[var(--line)]">
                <p class="text-sm font-semibold">Ảnh bìa</p>
                <p class="text-xs text-[var(--muted)] mt-0.5">Ảnh ngang lớn ở đầu trang (tỉ lệ khoảng 2:1). Tự nén nhẹ; mỗi mẫu trình bày một kiểu. Ảnh này cũng là ảnh xem trước khi bạn chia sẻ link lên Facebook, Zalo, Messenger.</p>
                <div class="mt-3 flex flex-wrap items-start gap-4">
                    @if($studio->coverUrl())
                        <img src="{{ $studio->coverUrl() }}" alt="Ảnh bìa hiện tại" class="w-56 aspect-[2/1] object-cover rounded-md ring-1 ring-[var(--line)]">
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('settings.page-cover') }}" enctype="multipart/form-data" x-data data-paste-zone="Ảnh bìa trang đặt đơn">
                            @csrf
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-md font-semibold bg-white border border-[var(--line-strong)] hover:border-[var(--ink)] cursor-pointer">
                                <i class="fa-solid fa-image" aria-hidden="true"></i>{{ $studio->cover_path ? 'Đổi ảnh bìa' : 'Tải ảnh bìa' }}
                                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="$el.form.requestSubmit()">
                            </label>
                            <x-paste-hint class="block mt-1.5" />
                        </form>
                        @if($studio->cover_path)
                            <form method="POST" action="{{ route('settings.page-cover') }}">@csrf<input type="hidden" name="remove" value="1">
                                <x-button size="sm" variant="ghost">Gỡ ảnh bìa</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tác phẩm tiêu biểu --}}
            <div class="mt-8 pt-6 border-t border-[var(--line)]">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold">Tác phẩm tiêu biểu</p>
                    <span class="text-xs text-[var(--muted)] tnum">{{ $works->count() }} / {{ $maxWorks }}</span>
                </div>
                <p class="text-xs text-[var(--muted)] mt-0.5">Khách xem ngay trên trang đặt đơn, bấm vào để xem lớn. Ảnh xem trước đã nén — file gốc vẫn ở máy bạn.</p>

                @if($works->isNotEmpty())
                    <ul class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach($works as $w)
                            <li class="rounded-lg border border-[var(--line)] bg-white p-2">
                                <img src="{{ $w->url() }}" alt="{{ $w->caption ?: 'Tác phẩm '.$loop->iteration }}" class="w-full aspect-[4/3] object-cover rounded" loading="lazy">
                                <form method="POST" action="{{ route('portfolio.update', $w->id) }}" class="mt-2 flex gap-1.5">
                                    @csrf @method('PUT')
                                    <label for="cap-{{ $w->id }}" class="sr-only">Chú thích</label>
                                    <input id="cap-{{ $w->id }}" name="caption" value="{{ $w->caption }}" maxlength="120" placeholder="Chú thích" class="min-w-0 flex-1 rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-xs">
                                    <button class="shrink-0 rounded-md px-2 text-xs font-semibold text-[var(--accent-ink)] hover:bg-stone-50">Lưu</button>
                                </form>
                                <div class="mt-1.5 flex items-center justify-between">
                                    @if(! $loop->first)
                                        <form method="POST" action="{{ route('portfolio.up', $w->id) }}">@csrf
                                            <button class="text-xs text-[var(--muted)] hover:text-[var(--ink)]"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Lên trước</button>
                                        </form>
                                    @else
                                        <span></span>
                                    @endif
                                    <form method="POST" action="{{ route('portfolio.destroy', $w->id) }}" onsubmit="return confirm('Xoá tác phẩm này khỏi trang đặt đơn?')">@csrf @method('DELETE')
                                        <button class="text-xs text-[var(--muted)] hover:text-red-600"><i class="fa-solid fa-trash-can mr-1" aria-hidden="true"></i>Xoá</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($works->count() < $maxWorks)
                    <form method="POST" action="{{ route('portfolio.store') }}" enctype="multipart/form-data" class="mt-4" x-data data-paste-zone="Tác phẩm tiêu biểu">
                        @csrf
                        <label class="inline-flex items-center gap-2 px-3 py-2 text-sm rounded-md font-semibold bg-white border border-dashed border-[var(--line-strong)] hover:border-[var(--ink)] cursor-pointer">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>Thêm ảnh tác phẩm (chọn được nhiều ảnh)
                            <input type="file" name="works[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="$el.form.requestSubmit()">
                        </label>
                        <x-paste-hint class="ml-2" />
                        @error('works')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </form>
                @endif
            </div>
        </section>

        {{-- Trợ lý AI --}}
        <section id="tro-ly-ai" class="{{ $box }}" aria-labelledby="h-ai">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="h-ai" class="font-display text-lg font-bold tracking-tight">Trợ lý AI</h2>
                <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $studio->hasAi() ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600' }}">{{ $studio->hasAi() ? 'Đang bật' : 'Chưa bật' }}</span>
            </div>
            <p class="text-sm text-[var(--muted)] mt-0.5">Dán đoạn chat là có đơn, soạn tin nhắn gửi khách, rà brief còn thiếu gì, gom góp ý thành việc cần sửa. Chạy bằng Google Gemini với API key <span class="font-semibold text-[var(--ink-2)]">miễn phí</span> của chính bạn.</p>

            @if($studio->hasAi())
                <div class="mt-4 rounded-lg bg-stone-50 border border-[var(--line)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm"><i class="fa-solid fa-key text-[var(--muted)] mr-1.5" aria-hidden="true"></i>Key đang dùng: <span class="font-mono tnum">{{ $studio->maskedGeminiKey() }}</span></p>
                        <form method="POST" action="{{ route('settings.ai.remove') }}" onsubmit="return confirm('Gỡ API key và tắt Trợ lý AI?')">@csrf @method('DELETE')
                            <button class="text-xs text-[var(--muted)] hover:text-red-600">Gỡ key</button>
                        </form>
                    </div>
                    <p class="text-xs font-semibold text-[var(--ink-2)] mt-4 mb-2">Lượt dùng hôm nay <span class="font-normal text-[var(--muted)]">— tự chuyển sang model sau khi model trước hết lượt; Google reset lúc {{ $aiResetAt->format('H:i') }}</span></p>
                    <ul class="space-y-2">
                        @foreach($aiUsage as $i => $m)
                            @php $pct = $m['daily'] > 0 ? min(100, (int) round($m['used'] / $m['daily'] * 100)) : 0; @endphp
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span>{{ $m['label'] }} @if($i === 0)<span class="text-xs text-[var(--muted)]">· thông minh nhất</span>@endif</span>
                                    <span class="tnum text-xs {{ $m['exhausted'] ? 'text-red-600 font-semibold' : 'text-[var(--muted)]' }}">
                                        @if($m['gone']) tạm không dùng được
                                        @elseif($m['exhausted']) hết lượt hôm nay
                                        @else {{ $m['used'] }} / ~{{ $m['daily'] }} lượt
                                        @endif
                                    </span>
                                </div>
                                <div class="h-1.5 rounded-full bg-white ring-1 ring-[var(--line)] mt-1" aria-hidden="true">
                                    <div class="h-full rounded-full {{ $m['exhausted'] ? 'bg-red-400' : 'bg-[var(--accent)]' }}" style="width: {{ $m['exhausted'] ? 100 : $pct }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-[var(--muted)] mt-3">Số lượt miễn phí do Google đặt và có thể thay đổi; con số ở đây chỉ đếm lượt dùng trong Nét.</p>
                </div>

                {{-- Chọn mô hình --}}
                @php
                    $maxModels = (int) config('net.ai.max_models', 3);
                    $currentModels = $studio->aiModelIds();
                    $slotLabels = ['Mô hình chính', 'Dự phòng 1', 'Dự phòng 2', 'Dự phòng 3', 'Dự phòng 4'];
                @endphp
                <div class="mt-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-semibold">Mô hình AI</p>
                        <span class="text-xs text-[var(--muted)]">{{ $studio->usesDefaultAiModels() ? 'Đang dùng mặc định của hệ thống' : 'Bạn đã tự chọn' }}</span>
                    </div>
                    <p class="text-xs text-[var(--muted)] mt-0.5">Thử lần lượt từ trên xuống — mô hình trên hết lượt hoặc lỗi thì tự chuyển xuống dưới. Để trống ô dự phòng nếu không cần.</p>
                    <form method="POST" action="{{ route('settings.ai.models') }}" class="mt-3 space-y-2">
                        @csrf @method('PUT')
                        <datalist id="ai-model-options">
                            @foreach($aiModelOptions as $opt)<option value="{{ $opt }}"></option>@endforeach
                        </datalist>
                        @for($i = 0; $i < $maxModels; $i++)
                            <x-input name="models[{{ $i }}]" id="ai-model-{{ $i }}" :label="$slotLabels[$i] ?? 'Dự phòng '.$i" list="ai-model-options"
                                     :value="old('models.'.$i, $currentModels[$i] ?? '')" placeholder="{{ $i === 0 ? 'gemini-3.5-flash' : 'gemini-3.5-flash-lite' }}" maxlength="80" autocomplete="off" />
                            @error('models.'.$i)<p class="-mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @endfor
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <x-button size="sm" icon="fa-check">Lưu mô hình</x-button>
                            @unless($studio->usesDefaultAiModels())
                                <button type="submit" name="reset" value="1" class="text-xs text-[var(--muted)] hover:text-[var(--ink)]">Dùng mặc định ({{ implode(' → ', config('net.ai.default_models')) }})</button>
                            @endunless
                        </div>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('settings.ai') }}" class="mt-5 space-y-3">
                @csrf @method('PUT')
                <x-input name="gemini_api_key" type="password" :label="$studio->hasAi() ? 'Đổi API key' : 'API key Gemini'" placeholder="AIza…" autocomplete="off" required maxlength="200" />
                <x-button icon="fa-wand-magic-sparkles">{{ $studio->hasAi() ? 'Lưu key mới' : 'Bật Trợ lý AI' }}</x-button>
            </form>

            <details class="mt-5 text-sm" @if(! $studio->hasAi()) open @endif>
                <summary class="cursor-pointer font-semibold text-[var(--ink-2)]">Lấy API key miễn phí (1 phút)</summary>
                <ol class="mt-2 ml-5 list-decimal space-y-1 text-[var(--ink-2)]">
                    <li>Mở <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="text-[var(--accent-ink)] font-semibold hover:underline">aistudio.google.com/apikey</a> và đăng nhập tài khoản Google.</li>
                    <li>Bấm <span class="font-semibold">Create API key</span>, sao chép chuỗi bắt đầu bằng <span class="font-mono">AIza…</span></li>
                    <li>Dán vào ô bên trên. Không cần thẻ thanh toán.</li>
                </ol>
            </details>
            <p class="mt-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-900 leading-relaxed">
                <i class="fa-solid fa-shield-halved mr-1" aria-hidden="true"></i>Khi bạn dùng AI, nội dung liên quan (đoạn chat bạn dán, brief, góp ý của khách) được gửi tới Google để xử lý. Với key miễn phí, Google có thể dùng dữ liệu này để cải thiện sản phẩm — đừng dán thông tin nhạy cảm (số tài khoản, CCCD…). Key được mã hoá khi lưu và chỉ dùng cho studio của bạn.
            </p>
        </section>

        {{-- Dữ liệu --}}
        <section id="du-lieu" class="{{ $box }}" aria-labelledby="h-data">
            <h2 id="h-data" class="font-display text-lg font-bold tracking-tight">Dữ liệu</h2>
            <div class="mt-4">
                <div class="flex items-baseline justify-between text-sm">
                    <span>Dung lượng ảnh đã dùng</span>
                    <span class="tnum text-[var(--muted)]">{{ round($studio->storage_bytes / 1048576, 1) }} / {{ round($studio->storageQuotaBytes() / 1048576) }} MB</span>
                </div>
                <div class="h-2 rounded-full bg-stone-100 mt-2" role="progressbar" aria-valuenow="{{ $studio->storageUsedPercent() }}" aria-valuemin="0" aria-valuemax="100" aria-label="Dung lượng đã dùng">
                    <div class="h-full rounded-full {{ $studio->storageUsedPercent() >= 90 ? 'bg-red-500' : 'bg-[var(--accent)]' }}" style="width: {{ $studio->storageUsedPercent() }}%"></div>
                </div>
                <p class="text-xs text-[var(--muted)] mt-2">Chỉ tính ảnh xem trước đã nén (bìa, bản thảo). File gốc nằm ở Google Drive của bạn, không tốn dung lượng ở đây.</p>
            </div>
            <div class="flex flex-wrap gap-2 mt-5">
                <x-button variant="outline" size="sm" icon="fa-download" :href="route('settings.export')">Tải toàn bộ dữ liệu (JSON)</x-button>
                <x-button variant="outline" size="sm" icon="fa-file-csv" :href="route('reports.export')">Danh sách đơn (CSV)</x-button>
            </div>
            <p class="text-sm text-[var(--muted)] mt-5">Muốn xoá tài khoản? Vào <a href="{{ route('profile.edit') }}#xoa-tai-khoan" class="underline">Hồ sơ cá nhân</a>.</p>
        </section>
    </div>
</div>
@endsection
