@extends('layouts.app')
@php
    $editing = $order->exists;
    $title = $editing ? 'Sửa đơn '.$order->code : 'Đơn mới';
    $servicesJson = $services->map(fn ($s) => ['id' => $s->id, 'rounds' => $s->effectiveRevisionRounds($studio), 'deposit' => $s->effectiveDepositPercent($studio), 'days' => $s->duration_days, 'hint' => $s->brief_hint])->keyBy('id');
    $linkRows = old('links', $editing ? [] : [['url' => '', 'label' => '']]);
@endphp
@section('title', $title.' · Nét')
@section('page-title', $title)
@section('content')
<div class="px-4 py-5 md:px-8 md:py-7 max-w-3xl mx-auto">
    @unless($editing)
        {{-- Lên đơn nhanh bằng AI: form riêng, gửi xong quay lại đây với các ô bên dưới đã điền sẵn để kiểm tra. --}}
        @if($studio->hasAi())
            <form method="POST" action="{{ route('orders.ai-draft') }}" class="mb-6 rounded-lg border-2 border-[var(--accent)] bg-white p-5" id="ai-len-don"
                  x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-[var(--accent)]" aria-hidden="true"></i>
                    <h2 class="font-display text-lg font-bold tracking-tight">Lên đơn nhanh bằng AI</h2>
                </div>
                <p class="text-sm text-[var(--muted)] mt-1">Dán đoạn chat Zalo/Messenger với khách, hoặc ghi vài dòng kiểu <span class="italic">“Chị Lan, fb.com/lan.bakery, logo tiệm bánh Mây tông kem nâu, 1tr5 cọc 50%, thứ 6 tuần sau giao”</span> — AI điền sẵn form bên dưới để bạn kiểm tra.</p>
                <div class="mt-3">
                    <label for="ai_text" class="sr-only">Nội dung đơn</label>
                    <textarea id="ai_text" name="ai_text" rows="5" maxlength="8000" required
                              class="w-full rounded-lg border {{ $errors->has('ai_text') ? 'border-red-400' : 'border-[var(--line-strong)]' }} bg-white px-3.5 py-2.5 text-[0.95rem] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)]"
                              placeholder="Dán đoạn chat hoặc ghi chú về đơn…">{{ old('ai_text') }}</textarea>
                    @error('ai_text')<p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button class="inline-flex items-center gap-2 rounded-lg bg-[var(--accent)] text-[var(--on-accent)] font-semibold px-4 py-2 text-sm disabled:opacity-60" :disabled="busy">
                        <i class="fa-solid" :class="busy ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'" aria-hidden="true"></i>
                        <span x-text="busy ? 'AI đang đọc… (10–30 giây)' : 'Lên đơn bằng AI'">Lên đơn bằng AI</span>
                    </button>
                    <span class="text-xs text-[var(--muted)]">AI chỉ điền sẵn — bạn vẫn xem lại rồi mới bấm “Tạo đơn”.</span>
                </div>
            </form>
        @else
            <a href="{{ route('settings.index') }}#tro-ly-ai" class="mb-6 flex items-center gap-3 rounded-lg border border-dashed border-[var(--line-strong)] bg-white px-4 py-3 text-sm hover:border-[var(--ink)]">
                <i class="fa-solid fa-wand-magic-sparkles text-[var(--accent)]" aria-hidden="true"></i>
                <span><span class="font-semibold">Ngại điền form?</span> <span class="text-[var(--muted)]">Bật Trợ lý AI miễn phí — dán đoạn chat với khách là có đơn.</span></span>
                <i class="fa-solid fa-arrow-right ml-auto text-[var(--muted)]" aria-hidden="true"></i>
            </a>
        @endif

        @if(session('ai_draft'))
            @php $draft = session('ai_draft'); @endphp
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                <p class="font-semibold"><i class="fa-solid fa-wand-magic-sparkles mr-1.5" aria-hidden="true"></i>AI đã điền sẵn đơn ({{ $draft['model'] }}) — kiểm tra lại từng ô rồi bấm “Tạo đơn”.</p>
                @if($draft['matched'])<p class="mt-1">Đã chọn khách có sẵn: <span class="font-semibold">{{ $draft['matched'] }}</span>.</p>@endif
                @if(! empty($draft['missing']))
                    <p class="mt-2 font-semibold">Còn thiếu, nên hỏi thêm khách:</p>
                    <ul class="mt-1 ml-5 list-disc space-y-0.5">@foreach($draft['missing'] as $m)<li>{{ $m }}</li>@endforeach</ul>
                @endif
            </div>
        @endif
    @endunless

    <x-errors class="mb-5" />

    <form method="POST" action="{{ $editing ? route('orders.update', $order->id) : route('orders.store') }}" enctype="multipart/form-data" class="space-y-6"
          x-data="{ services: @js($servicesJson), serviceId: @js((string) old('service_id', $order->service_id)), clientMode: @js(old('client_id', $order->client_id) || $editing ? 'existing' : (($clients->isEmpty() || filled(old('client_name'))) ? 'new' : 'existing')),
                    rounds: {{ (int) old('revision_rounds', $order->revision_rounds) }}, deposit: {{ (int) old('deposit_percent', $order->deposit_percent) }}, due: @js(old('due_on', $order->due_on?->toDateString())),
                    get hint() { return this.services[this.serviceId]?.hint || '' },
                    pickService() { const s = this.services[this.serviceId]; if (!s || {{ $editing ? 'true' : 'false' }}) return; this.rounds = s.rounds; this.deposit = s.deposit;
                        if (s.days && !this.due) { const d = new Date(); d.setDate(d.getDate() + s.days); this.due = d.toISOString().slice(0, 10); } } }">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- Khách --}}
        <fieldset class="rounded-lg border border-[var(--line)] bg-white p-5 space-y-4">
            <legend class="font-display text-lg font-bold tracking-tight px-1">Khách hàng</legend>
            @unless($editing)
                <div class="inline-grid grid-cols-2 bg-stone-100 rounded-md p-1 text-sm" role="group" aria-label="Chọn khách">
                    <button type="button" class="px-3 py-1.5 rounded" :class="clientMode === 'existing' ? 'bg-white shadow-sm font-semibold' : 'text-[var(--muted)]'" @click="clientMode = 'existing'" :aria-pressed="(clientMode === 'existing').toString()">Khách có sẵn</button>
                    <button type="button" class="px-3 py-1.5 rounded" :class="clientMode === 'new' ? 'bg-white shadow-sm font-semibold' : 'text-[var(--muted)]'" @click="clientMode = 'new'" :aria-pressed="(clientMode === 'new').toString()">Khách mới</button>
                </div>
            @endunless
            <div x-show="clientMode === 'existing'" @if(! $editing && $clients->isEmpty()) x-cloak @endif>
                <x-select name="client_id" label="Khách" x-bind:disabled="clientMode !== 'existing'">
                    <option value="">— Chọn khách —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected((int) old('client_id', $order->client_id) === $c->id)>{{ $c->name }}{{ $c->company ? ' · '.$c->company : '' }}{{ ($c->phone || $c->email || $c->facebook) ? ' · '.$c->contactLine() : '' }}</option>
                    @endforeach
                </x-select>
            </div>
            @unless($editing)
                <div x-show="clientMode === 'new'" x-cloak class="grid sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2"><x-input name="client_name" label="Tên khách" value="{{ old('client_name') }}" maxlength="100" x-bind:disabled="clientMode !== 'new'" /></div>
                    <p class="sm:col-span-2 text-xs text-[var(--muted)] -mb-1">Cần ít nhất 1 trong 3: số điện thoại, email hoặc Facebook. Trùng với khách có sẵn thì Nét tự gắn vào khách đó.</p>
                    <x-input name="client_phone" label="Số điện thoại / Zalo" value="{{ old('client_phone') }}" inputmode="tel" maxlength="20" x-bind:disabled="clientMode !== 'new'" />
                    <x-input type="email" name="client_email" label="Email" value="{{ old('client_email') }}" maxlength="255" x-bind:disabled="clientMode !== 'new'" />
                    <div class="sm:col-span-2"><x-input name="client_facebook" label="Facebook" value="{{ old('client_facebook') }}" maxlength="255" placeholder="facebook.com/ten.khach hoặc tên Facebook" x-bind:disabled="clientMode !== 'new'" /></div>
                </div>
            @endunless
        </fieldset>

        {{-- Đơn --}}
        <fieldset class="rounded-lg border border-[var(--line)] bg-white p-5 space-y-4">
            <legend class="font-display text-lg font-bold tracking-tight px-1">Đơn</legend>
            <x-input name="title" label="Tên đơn" value="{{ old('title', $order->title) }}" required maxlength="160" placeholder="VD: Logo tiệm bánh Mây, Bộ 10 post Tết" />
            <div class="grid sm:grid-cols-2 gap-3">
                <x-select name="service_id" label="Dịch vụ" x-model="serviceId" x-on:change="pickService()">
                    <option value="">— Không chọn —</option>
                    @foreach($services as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->priceLabel() }})</option>@endforeach
                </x-select>
                <x-select name="priority" label="Mức ưu tiên">
                    @foreach(config('net.priorities') as $k => $l)<option value="{{ $k }}" @selected(old('priority', $order->priority) === $k)>{{ $l }}</option>@endforeach
                </x-select>
            </div>
            @unless($editing)
                <x-select name="status" label="Bắt đầu ở giai đoạn" hint="Đơn thoả thuận xong qua tin nhắn thì chọn Đang làm.">
                    @foreach(['in_progress', 'quoted', 'new'] as $k)<option value="{{ $k }}" @selected(old('status', 'in_progress') === $k)>{{ config('net.statuses.'.$k.'.label') }}</option>@endforeach
                </x-select>
                <p class="text-xs text-[var(--muted)] -mt-2" x-show="!!serviceId && services[serviceId] && {{ $editing ? 'false' : 'true' }}" x-cloak>Giá của dịch vụ được thêm sẵn vào báo giá — sửa được ở trang đơn.</p>
            @endunless
        </fieldset>

        {{-- Brief --}}
        <fieldset class="rounded-lg border border-[var(--line)] bg-white p-5 space-y-4">
            <legend class="font-display text-lg font-bold tracking-tight px-1">Brief</legend>
            <p class="text-sm text-[var(--muted)] -mt-1 rounded-md bg-stone-50 border border-[var(--line)] px-3 py-2" x-show="hint" x-cloak x-text="'Gợi ý: ' + hint"></p>
            <x-textarea name="brief" label="Yêu cầu" rows="5" required placeholder="Thiết kế để làm gì, dành cho ai, cần những gì…">{{ old('brief', $order->brief) }}</x-textarea>
            <div class="grid sm:grid-cols-2 gap-3">
                <x-input name="style" label="Phong cách" value="{{ old('style', $order->style) }}" maxlength="500" placeholder="Tối giản, cổ điển, dễ thương…" />
                <x-input name="colors" label="Màu sắc" value="{{ old('colors', $order->colors) }}" maxlength="255" placeholder="Xanh rêu, kem; tránh đỏ" />
                <x-input name="specs" label="Kích thước, định dạng" value="{{ old('specs', $order->specs) }}" maxlength="500" placeholder="1080×1350, in A4…" />
                <x-input name="budget_note" label="Ngân sách khách nêu" value="{{ old('budget_note', $order->budget_note) }}" maxlength="120" />
            </div>
            <x-textarea name="copy_text" label="Nội dung chữ trên thiết kế" rows="3">{{ old('copy_text', $order->copy_text) }}</x-textarea>

            @unless($editing)
                <div x-data="{ links: @js(array_values($linkRows)) }">
                    <p class="text-sm font-medium text-[var(--ink-2)] mb-1.5">Link tài liệu (Google Drive, Figma, Pinterest…)</p>
                    <template x-for="(l, i) in links" :key="i">
                        <div class="grid grid-cols-[1fr_auto] sm:grid-cols-[1fr_200px_auto] gap-2 mb-2">
                            <input type="url" :name="`links[${i}][url]`" x-model="l.url" placeholder="https://drive.google.com/…" aria-label="Link" class="rounded-lg border border-[var(--line-strong)] px-3 py-2 text-sm">
                            <input :name="`links[${i}][label]`" x-model="l.label" placeholder="Tên link" aria-label="Tên link" class="hidden sm:block rounded-lg border border-[var(--line-strong)] px-3 py-2 text-sm">
                            <button type="button" @click="links.splice(i, 1)" class="text-[var(--muted)] hover:text-red-600 px-2" aria-label="Bỏ link"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </div>
                    </template>
                    <button type="button" class="text-sm text-[var(--accent-ink)] font-semibold" x-show="links.length < 10" @click="links.push({ url: '', label: '' })">+ Thêm link</button>
                </div>
            @endunless
        </fieldset>

        {{-- Hạn & điều khoản --}}
        <fieldset class="rounded-lg border border-[var(--line)] bg-white p-5 space-y-4">
            <legend class="font-display text-lg font-bold tracking-tight px-1">Hạn & thoả thuận</legend>
            <div class="grid sm:grid-cols-2 gap-3">
                <x-input type="date" name="due_on" label="Hạn bàn giao" x-model="due" />
                <x-input type="date" name="client_due_on" label="Khách mong nhận trước" value="{{ old('client_due_on', $order->client_due_on?->toDateString()) }}" />
                <x-input type="number" name="revision_rounds" label="Số lần sửa bao gồm" min="0" max="20" x-model.number="rounds" required />
                <x-input type="number" name="deposit_percent" label="Đặt cọc (%)" min="0" max="100" x-model.number="deposit" required />
            </div>
            @unless($editing)
                <x-input name="price" label="Giá đã chốt (đ)" value="{{ old('price') }}" inputmode="numeric" maxlength="20" placeholder="VD: 1500000"
                         hint="Không bắt buộc. Để trống thì lấy giá của dịch vụ đã chọn làm báo giá." />
            @endunless
            <input type="hidden" name="lock_delivery" value="0">
            <label class="flex items-start gap-2.5">
                <input type="checkbox" name="lock_delivery" value="1" class="mt-1 w-4 h-4 accent-[var(--accent)]" @checked(old('lock_delivery', $order->lock_delivery))>
                <span class="text-sm">Khoá link file bàn giao tới khi khách thanh toán đủ</span>
            </label>
            <x-textarea name="internal_note" label="Ghi chú riêng (khách không thấy)" rows="2">{{ old('internal_note', $order->internal_note) }}</x-textarea>
        </fieldset>

        @unless($editing)
            <fieldset class="rounded-lg border border-[var(--line)] bg-white p-5" x-data="filePreview(1)" data-paste-zone="Ảnh bìa đơn">
                <legend class="font-display text-lg font-bold tracking-tight px-1">Ảnh bìa <span class="font-sans text-sm font-normal text-[var(--muted)]">(không bắt buộc)</span></legend>
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" @change="pick" aria-label="Ảnh bìa" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold">
                <x-paste-hint class="block mt-1.5" prefix="Hoặc" />
                <template x-if="previews.length"><img :src="previews[0].url" alt="" class="mt-3 w-60 rounded-md ring-1 ring-[var(--line)] aspect-[16/10] object-cover"></template>
            </fieldset>
        @endunless

        <div class="flex flex-wrap items-center gap-3">
            <x-button icon="fa-check">{{ $editing ? 'Lưu thay đổi' : 'Tạo đơn' }}</x-button>
            <a href="{{ $editing ? route('orders.show', $order->id) : route('orders.index') }}" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">Huỷ</a>
        </div>
    </form>
</div>
@endsection
