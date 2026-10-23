{{-- Cột phụ của trang đơn. Biến: $order, $studio, $qr, $message --}}
@php
    $chargeRows = $order->charges->map(fn ($c) => ['kind' => $c->kind, 'label' => $c->label, 'amount' => (int) round((float) $c->amount)])->values();
    if ($chargeRows->isEmpty()) {
        $chargeRows = collect([['kind' => 'base', 'label' => $order->service?->name ?? 'Phí thiết kế', 'amount' => (int) round((float) ($order->service?->price ?? 0))]]);
    }
    $sources = $order->links->where('kind', 'source');
@endphp

{{-- Ảnh bìa --}}
<section class="rounded-lg border border-[var(--line)] bg-white p-4" x-data="filePreview(1)" data-paste-zone="Ảnh bìa đơn">
    <p class="text-sm font-semibold mb-2">Ảnh bìa đơn</p>
    <form method="POST" action="{{ route('orders.cover', $order->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
        @csrf
        <label class="sr-only" for="cover">Chọn ảnh bìa</label>
        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" required @change="pick" class="text-sm w-full text-[var(--muted)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--ink)] file:text-white file:cursor-pointer file:px-3 file:py-1.5 file:text-sm file:font-semibold">
        <template x-if="previews.length"><img :src="previews[0].url" alt="" class="w-full rounded-md ring-1 ring-[var(--line)] aspect-[16/10] object-cover"></template>
        <x-button size="sm" variant="outline" icon="fa-image">{{ $order->cover_path ? 'Đổi ảnh bìa' : 'Đặt ảnh bìa' }}</x-button>
    </form>
    @if($order->cover_path)
        <form method="POST" action="{{ route('orders.cover.remove', $order->id) }}" class="mt-2">@csrf @method('DELETE')
            <button class="text-xs text-[var(--muted)] hover:text-red-600">Gỡ ảnh bìa</button>
        </form>
    @endif
    <p class="text-xs text-[var(--muted)] mt-2">Giúp nhận ra đơn nhanh trên Bảng việc. Ảnh tự nén nhẹ. <x-paste-hint prefix="Có thể" /></p>
</section>

{{-- Báo giá & thanh toán --}}
<section id="bao-gia" class="scroll-mt-20 rounded-lg border border-[var(--line)] bg-white">
    <div class="px-4 pt-4 flex items-center justify-between gap-2">
        <p class="text-sm font-semibold">Báo giá</p>
        @if($order->quote_accepted_at)
            <span class="text-xs font-semibold text-emerald-700"><i class="fa-solid fa-handshake mr-1" aria-hidden="true"></i>Khách đã đồng ý</span>
        @elseif($order->quote_sent_at)
            <span class="text-xs text-[var(--muted)]">Đã gửi {{ $order->quote_sent_at->format('d/m') }}, chờ khách</span>
        @endif
    </div>
    <form method="POST" action="{{ route('orders.charges', $order->id) }}" class="p-4 space-y-2"
          x-data="{ rows: @js($chargeRows), dep: {{ (int) $order->deposit_percent }},
                    get total() { return Math.max(0, this.rows.reduce((s, r) => s + (r.kind === 'discount' ? -1 : 1) * (parseInt(String(r.amount).replace(/\D/g, '')) || 0), 0)) },
                    fmt(n) { return Number(n).toLocaleString('vi-VN') } }">
        @csrf @method('PUT')
        <template x-for="(r, i) in rows" :key="i">
            <div class="grid grid-cols-[minmax(0,1fr)_7rem_1.75rem] gap-1.5 pb-2 border-b border-dashed border-[var(--line)]">
                <input :id="'charge-label-' + i" :name="`charges[${i}][label]`" x-model="r.label" maxlength="160" placeholder="Nội dung" aria-label="Nội dung" class="col-span-2 min-w-0 rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
                <button type="button" @click="rows.splice(i, 1)" class="text-[var(--muted)] hover:text-red-600 px-1" aria-label="Xoá dòng"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                <select :id="'charge-kind-' + i" :name="`charges[${i}][kind]`" x-model="r.kind" aria-label="Loại" class="min-w-0 rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-xs bg-white">
                    @foreach(config('net.charge_kinds') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input :id="'charge-amount-' + i" :value="r.amount ? fmt(r.amount) : ''" @input="r.amount = $event.target.value.replace(/\D/g, ''); $event.target.value = r.amount ? fmt(r.amount) : ''"
                       inputmode="numeric" aria-label="Số tiền" placeholder="0" class="w-full min-w-0 rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm text-right tnum">
                <input type="hidden" :name="`charges[${i}][amount]`" :value="r.amount"><span aria-hidden="true"></span>
            </div>
        </template>
        <button type="button" class="text-sm text-[var(--accent-ink)] font-semibold" @click="rows.push({ kind: 'extra', label: '', amount: '' })">+ Thêm dòng</button>

        <div class="flex items-center justify-between pt-1">
            <span class="text-sm text-[var(--muted)]">Tổng</span>
            <span class="font-display text-xl font-bold tnum" x-text="fmt(total) + 'đ'">{{ money($order->total_amount) }}đ</span>
        </div>
        <div class="flex items-center justify-between gap-2 text-sm">
            <label for="dep">Đặt cọc</label>
            <span class="flex items-center gap-1.5"><input id="dep" type="number" name="deposit_percent" x-model.number="dep" min="0" max="100" class="w-16 rounded-md border border-[var(--line-strong)] px-2 py-1 text-right tnum">%
                <span class="text-[var(--muted)] tnum" x-text="'= ' + fmt(Math.min(total, Math.ceil(total * dep / 100 / 1000) * 1000)) + 'đ'"></span></span>
        </div>
        @error('charges')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <div class="flex gap-2 pt-1">
            <x-button size="sm" variant="outline" class="flex-1">Lưu</x-button>
            @unless($order->isCancelled())
                <x-button size="sm" variant="ink" name="send" value="1" class="flex-1" icon="fa-paper-plane">{{ $order->quote_sent_at ? 'Lưu & gửi lại' : 'Lưu & gửi khách' }}</x-button>
            @endunless
        </div>
    </form>

    {{-- Thu tiền --}}
    <div class="border-t border-[var(--line)] p-4 space-y-3">
        <div class="grid grid-cols-3 gap-2 text-center">
            <div><p class="text-[0.7rem] text-[var(--muted)]">Tổng</p><p class="text-sm font-bold tnum">{{ money_compact($order->total_amount) }}</p></div>
            <div><p class="text-[0.7rem] text-[var(--muted)]">Đã thu</p><p class="text-sm font-bold text-emerald-700 tnum">{{ money_compact($order->paid_amount) }}</p></div>
            <div><p class="text-[0.7rem] text-[var(--muted)]">Còn lại</p><p class="text-sm font-bold tnum {{ $order->balance() > 0 ? 'text-red-600' : 'text-[var(--muted)]' }}">{{ money_compact(max(0, $order->balance())) }}</p></div>
        </div>

        @foreach($order->payments as $p)
            <div class="flex items-center gap-2 text-sm">
                <span class="tnum font-semibold">{{ money($p->amount) }}đ</span>
                <span class="text-xs text-[var(--muted)] truncate">{{ $p->is_deposit ? 'cọc · ' : '' }}{{ $p->methodLabel() }} · {{ $p->paid_on->format('d/m') }}@if($p->note) · {{ $p->note }}@endif</span>
                <form method="POST" action="{{ route('payments.destroy', $p->id) }}" class="ml-auto" onsubmit="return confirm('Xoá khoản thu {{ money($p->amount) }}đ?')">@csrf @method('DELETE')
                    <button class="text-[var(--muted)] hover:text-red-600 p-1" aria-label="Xoá khoản thu"><i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i></button>
                </form>
            </div>
        @endforeach

        @if($order->balance() > 0)
            <details class="rounded-md border border-[var(--line)]" @if($errors->has('amount')) open @endif>
                <summary class="cursor-pointer list-none px-3 py-2 text-sm font-semibold"><i class="fa-solid fa-hand-holding-dollar text-[var(--accent)] mr-1.5" aria-hidden="true"></i>Ghi nhận đã nhận tiền</summary>
                <form method="POST" action="{{ route('orders.pay', $order->id) }}" class="px-3 pb-3 space-y-2">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <input name="amount" class="money-input rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm tnum" inputmode="numeric" aria-label="Số tiền" value="{{ (int) round($order->nextPaymentAmount() ?: max(0, $order->balance())) }}" required>
                        <select name="method" aria-label="Hình thức" class="rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-sm bg-white">@foreach(config('net.payment_methods') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <input type="date" name="paid_on" aria-label="Ngày nhận" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-sm" required>
                        <input name="note" aria-label="Ghi chú" placeholder="Ghi chú" maxlength="255" class="rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
                    </div>
                    @error('amount')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <x-button size="sm" variant="success" icon="fa-check" class="w-full">Đã nhận tiền</x-button>
                </form>
            </details>
            @if(! $studio->hasVietQr())
                <p class="text-xs text-[var(--muted)]"><a href="{{ route('settings.index') }}#thanh-toan" class="underline">Thêm tài khoản ngân hàng</a> để khách quét mã VietQR ngay trên trang theo dõi.</p>
            @endif
        @endif
    </div>
</section>

{{-- Bàn giao --}}
<section class="rounded-lg border border-[var(--line)] bg-white p-4">
    <div class="flex items-center justify-between gap-2 mb-2">
        <p class="text-sm font-semibold">Bàn giao file</p>
        @if($sources->isNotEmpty())
            <span class="text-xs {{ $order->deliveryUnlocked() ? 'text-emerald-700' : 'text-amber-700' }}"><i class="fa-solid {{ $order->deliveryUnlocked() ? 'fa-lock-open' : 'fa-lock' }} mr-1" aria-hidden="true"></i>{{ $order->deliveryUnlocked() ? 'Khách tải được' : 'Khoá tới khi thu đủ' }}</span>
        @endif
    </div>
    @foreach($sources as $link)
        <div class="flex items-center gap-2 py-1 text-sm">
            <i class="{{ link_icon($link->url) }} w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>
            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate hover:underline">{{ $link->displayLabel() }}</a>
            <form method="POST" action="{{ route('links.destroy', $link->id) }}" class="ml-auto" onsubmit="return confirm('Gỡ link bàn giao này?')">@csrf @method('DELETE')
                <button class="text-[var(--muted)] hover:text-red-600 p-1" aria-label="Gỡ link"><i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i></button>
            </form>
        </div>
    @endforeach

    @if($order->isDelivered())
        <form method="POST" action="{{ route('orders.links.store', $order->id) }}" class="mt-2 flex gap-2">
            @csrf <input type="hidden" name="kind" value="source">
            <label class="sr-only" for="more-src">Thêm link bàn giao</label>
            <input id="more-src" type="url" name="url" required placeholder="Thêm link file…" class="flex-1 min-w-0 rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
            <x-button size="sm" variant="outline">Thêm</x-button>
        </form>
    @elseif(! $order->isCancelled())
        <form method="POST" action="{{ route('orders.deliver', $order->id) }}" class="space-y-2 mt-1" onsubmit="return confirm('Bàn giao đơn {{ $order->code }}?')">
            @csrf
            <label class="block text-xs text-[var(--muted)]" for="src-url">Link thư mục file gốc trên Google Drive</label>
            <input id="src-url" type="url" name="links[0][url]" @if($sources->isEmpty()) required @endif placeholder="https://drive.google.com/…" class="w-full rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
            <input name="links[0][label]" maxlength="160" placeholder="Tên (VD: File gốc AI + PNG)" aria-label="Tên link" class="w-full rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
            <textarea name="note" rows="2" maxlength="2000" placeholder="Lời nhắn kèm (không bắt buộc)" aria-label="Lời nhắn kèm" class="w-full rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm"></textarea>
            @error('links')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <x-button size="sm" variant="ink" icon="fa-box-archive" class="w-full">Bàn giao</x-button>
            @if($order->lock_delivery && $order->balance() > 0)
                <p class="text-xs text-[var(--muted)]">Khách còn nợ {{ money($order->balance()) }}đ nên link được khoá tới khi bạn ghi nhận thu đủ.</p>
            @endif
        </form>
    @endif
</section>

{{-- Trợ lý AI --}}
@php
    $aiFeedbackRound = $order->rounds->filter(fn ($r) => $r->responded_at && $r->feedback->isNotEmpty())->sortByDesc('number')->first();
    $aiResult = session('ai_result');
@endphp
<section id="tro-ly-ai" class="scroll-mt-20 rounded-lg border border-[var(--line)] bg-white p-4" x-data="{ busy: null }">
    <p class="text-sm font-semibold"><i class="fa-solid fa-wand-magic-sparkles text-[var(--accent)] mr-1.5" aria-hidden="true"></i>Trợ lý AI</p>
    @if(! $studio->hasAi())
        <p class="text-sm text-[var(--muted)] mt-1">Soạn tin nhắn gửi khách, rà brief còn thiếu, gom góp ý thành việc cần sửa.
            <a href="{{ route('settings.index') }}#tro-ly-ai" class="text-[var(--accent-ink)] font-semibold hover:underline">Bật miễn phí</a></p>
    @else
        @if(session('ai_error'))
            <p class="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ session('ai_error') }}</p>
        @endif

        @if($aiResult)
            <div class="mt-3 rounded-md bg-[var(--accent-soft)]/50 border border-[var(--accent-line)] p-3 space-y-2" role="status">
                <p class="text-sm font-semibold">{{ $aiResult['title'] }}</p>
                @if(! empty($aiResult['summary']))<p class="text-sm text-[var(--ink-2)]">{{ $aiResult['summary'] }}</p>@endif
                @if(array_key_exists('items', $aiResult))
                    @if($aiResult['items'])
                        <ul class="ml-4 list-disc space-y-1 text-sm">@foreach($aiResult['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                    @elseif(($aiResult['copy_title'] ?? '') === 'Tin nhắn hỏi khách')
                        <p class="text-sm text-emerald-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Brief đã khá đủ để bắt tay làm.</p>
                    @endif
                @endif
                @if(filled($aiResult['text'] ?? null))
                    <x-copy-block :text="$aiResult['text']" :title="$aiResult['copy_title'] ?? 'Sao chép gửi khách'" />
                @endif
                <p class="text-[0.7rem] text-[var(--muted)]">Soạn bởi {{ $aiResult['model'] }} — đọc lại trước khi gửi.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('orders.ai', $order->id) }}" class="mt-3 space-y-2" @submit="busy = 'message'">
            @csrf
            <input type="hidden" name="task" value="message">
            <label for="ai-situation" class="block text-xs text-[var(--muted)]">Soạn tin nhắn gửi khách</label>
            <select id="ai-situation" name="situation" class="w-full rounded-md border border-[var(--line-strong)] bg-white py-1.5 px-2.5 text-sm">
                @foreach(\App\Services\Ai\Assistant::SITUATIONS as $k => $label)
                    <option value="{{ $k }}" @selected(old('situation', $order->balance() > 0 && in_array($order->status, ['approved', 'delivered'], true) ? 'payment' : 'progress') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="ai-note" class="sr-only">Ghi chú thêm cho AI</label>
            <textarea id="ai-note" name="note" rows="2" maxlength="1000" placeholder="Ghi thêm (không bắt buộc): lý do, hạn mới, mức phí…" class="w-full rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm">{{ old('note') }}</textarea>
            @error('note')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-[var(--ink)] text-white text-sm font-semibold px-3 py-2 disabled:opacity-60" :disabled="busy !== null">
                <i class="fa-solid" :class="busy === 'message' ? 'fa-spinner fa-spin' : 'fa-pen-fancy'" aria-hidden="true"></i><span x-text="busy === 'message' ? 'Đang soạn…' : 'Soạn tin nhắn'">Soạn tin nhắn</span>
            </button>
        </form>

        <div class="mt-3 pt-3 border-t border-[var(--line)] grid gap-2">
            <form method="POST" action="{{ route('orders.ai', $order->id) }}" @submit="busy = 'brief'">
                @csrf <input type="hidden" name="task" value="brief">
                <button class="w-full inline-flex items-center gap-2 rounded-md border border-[var(--line-strong)] bg-white hover:border-[var(--ink)] text-sm font-semibold px-3 py-2 text-left disabled:opacity-60" :disabled="busy !== null">
                    <i class="fa-solid w-4" :class="busy === 'brief' ? 'fa-spinner fa-spin' : 'fa-list-check'" aria-hidden="true"></i>Brief còn thiếu gì?
                </button>
            </form>
            @if($aiFeedbackRound)
                <form method="POST" action="{{ route('orders.ai', $order->id) }}" @submit="busy = 'feedback'">
                    @csrf <input type="hidden" name="task" value="feedback">
                    <button class="w-full inline-flex items-center gap-2 rounded-md border border-[var(--line-strong)] bg-white hover:border-[var(--ink)] text-sm font-semibold px-3 py-2 text-left disabled:opacity-60" :disabled="busy !== null">
                        <i class="fa-solid w-4" :class="busy === 'feedback' ? 'fa-spinner fa-spin' : 'fa-clipboard-list'" aria-hidden="true"></i>Góp ý vòng {{ $aiFeedbackRound->label() }} → việc cần sửa
                    </button>
                </form>
            @endif
        </div>
        <p class="text-[0.7rem] text-[var(--muted)] mt-2">AI chỉ soạn sẵn, không tự gửi khách. Mỗi lần bấm dùng 1 lượt Gemini.</p>
    @endif
</section>

{{-- Gửi khách --}}
<section class="space-y-3">
    <x-copy-block :text="$message" title="Tin nhắn gửi khách" />
    <div class="rounded-lg border border-[var(--line)] bg-white p-4 text-sm" x-data="{ copied: false }">
        <p class="font-semibold mb-1">Link theo dõi của khách</p>
        <p class="text-xs text-[var(--muted)] break-all">{{ $order->trackingUrl() }}</p>
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2">
            <button type="button" class="text-xs font-semibold text-[var(--accent-ink)]" @click="copyText(@js($order->trackingUrl())).then(() => { copied = true; setTimeout(() => copied = false, 1500) })" x-text="copied ? 'Đã chép' : 'Sao chép link'">Sao chép link</button>
            <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener" class="text-xs text-[var(--muted)] hover:text-[var(--ink)]">Mở như khách</a>
            <form method="POST" action="{{ route('orders.token', $order->id) }}" onsubmit="return confirm('Tạo link mới? Link cũ sẽ không mở được nữa.')">@csrf
                <button class="text-xs text-[var(--muted)] hover:text-[var(--ink)]">Đổi link</button>
            </form>
        </div>
    </div>
</section>

{{-- Nhắc khách qua email --}}
@php
    $rp = $studio->reminderPrefs();
    $mail = $order->client ? $order->client->emailCheck() : ['ok' => false, 'reason' => 'Chưa có email', 'suggestion' => null];
    $sentReminders = $order->reminders()->limit(3)->get();
@endphp
@if($order->isOpen() || $order->balance() > 0)
    <section class="rounded-lg border border-[var(--line)] bg-white p-4" aria-labelledby="h-remind-{{ $order->id }}">
        <p id="h-remind-{{ $order->id }}" class="text-sm font-semibold mb-1.5"><i class="fa-regular fa-envelope mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>Nhắc khách qua email</p>
        @if(! $rp['enabled'])
            <p class="text-xs text-[var(--muted)]">Đang tắt cho cả studio. <a href="{{ route('settings.index') }}#nhac-khach" class="font-semibold text-[var(--accent-ink)] hover:underline">Bật ở Cài đặt</a></p>
        @elseif(! $mail['ok'])
            <p class="text-xs text-amber-800">{{ $mail['reason'] === 'Chưa có email' ? 'Khách chưa có email — Nét không gửi nhắc.' : 'Không gửi: '.$mail['reason'].'.' }}
                @if($mail['suggestion'])<span class="block mt-0.5">Có phải <strong>{{ $mail['suggestion'] }}</strong>? Sửa trong hồ sơ khách.</span>@endif
            </p>
        @else
            <p class="text-xs text-[var(--muted)]">{{ $order->reminders_muted ? 'Đã tắt cho đơn này.' : 'Tự nhắc tới '.$order->client->email.' khi đơn chờ khách quá lâu.' }}</p>
            <form method="POST" action="{{ route('orders.reminders', $order->id) }}" class="mt-2">@csrf @method('PATCH')
                <input type="hidden" name="on" value="{{ $order->reminders_muted ? 1 : 0 }}">
                <button class="text-xs font-semibold {{ $order->reminders_muted ? 'text-[var(--accent-ink)]' : 'text-[var(--muted)] hover:text-[var(--ink)]' }}">{{ $order->reminders_muted ? 'Bật lại nhắc cho đơn này' : 'Tắt nhắc cho đơn này' }}</button>
            </form>
        @endif
        @if($sentReminders->isNotEmpty())
            <ul class="mt-2 pt-2 border-t border-[var(--line)] space-y-1 text-xs text-[var(--muted)]">
                @foreach($sentReminders as $r)<li>{{ $r->sent_at->format('d/m H:i') }} · {{ $r->label() }}</li>@endforeach
            </ul>
        @endif
    </section>
@endif

@if($order->internal_note)
    <section class="rounded-lg border border-amber-200 bg-amber-50/60 p-4">
        <p class="text-xs font-semibold text-amber-800 mb-1"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Ghi chú riêng (khách không thấy)</p>
        <p class="text-sm whitespace-pre-line">{{ $order->internal_note }}</p>
    </section>
@endif

@if($order->payments->isEmpty())
    <form method="POST" action="{{ route('orders.destroy', $order->id) }}" class="text-center" onsubmit="return confirm('XOÁ VĨNH VIỄN đơn {{ $order->code }} cùng phương án, ảnh, trao đổi? Không thể hoàn tác.')">
        @csrf @method('DELETE')
        <button class="text-xs text-red-600 hover:underline"><i class="fa-solid fa-trash-can mr-1" aria-hidden="true"></i>Xoá đơn</button>
    </form>
@endif
