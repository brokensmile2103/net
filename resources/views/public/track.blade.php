@extends('layouts.public')
@section('title', $order->code.' · '.$order->title.' · '.$studio->name)
@section('share_title', 'Đơn '.$order->code.' · '.$order->title)
@section('meta_description', 'Theo dõi đơn '.$order->code.' với '.$studio->name.': tiến độ, phương án thiết kế, góp ý, thanh toán và file bàn giao.')
@section('content')
@php
    $steps = config('net.client_steps');
    $stepIndex = $order->clientStepIndex();
    $clientLabel = $order->meta()['client_label'] ?? $order->statusLabel();
    $pastRounds = $order->rounds->where('status', '!=', 'sent')->sortByDesc('number');
    $canAccept = ! $order->isCancelled() && $order->quote_sent_at && ! $order->quote_accepted_at && (float) $order->total_amount > 0;
    $showQuote = (float) $order->total_amount > 0 && ($order->quote_sent_at || $order->status !== 'new');
    $sources = $order->sourceLinks();
    $references = $order->referenceLinks();
@endphp

{{-- Vừa gửi đơn --}}
@if(session('placed'))
    <div class="pf-card mb-6 rounded-lg border-2 border-[var(--accent)] bg-white p-5" x-data="{ copied: false }" role="status">
        <p class="font-display text-xl font-bold">Đã gửi yêu cầu!</p>
        <p class="text-sm text-[var(--ink-2)] mt-1">{{ $studio->name }} sẽ xem và báo giá cho bạn sớm. <strong>Hãy lưu link trang này</strong> — đây là nơi bạn theo dõi tiến độ, xem phương án và góp ý.</p>
        <div class="flex flex-wrap gap-2 mt-3">
            <x-button type="button" size="sm" variant="ink" icon="fa-copy" @click="copyText(location.href.split('#')[0]).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"><span x-text="copied ? 'Đã chép link' : 'Chép link'">Chép link</span></x-button>
        </div>
    </div>
@endif

<x-errors class="mb-5" />

{{-- Đầu trang --}}
<section class="mb-6">
    @if($order->coverUrl())
        <x-cover :order="$order" ratio="16 / 7" class="mb-5" />
    @endif
    <p class="text-sm text-[var(--muted)] tnum">Đơn {{ $order->code }} · gửi {{ $order->created_at->format('d/m/Y') }}</p>
    <h1 class="font-display text-3xl md:text-4xl font-bold tracking-tight mt-1">{{ $order->title }}</h1>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-sm">
        <x-status :order="$order" :client="true" />
        @if($order->due_on && $order->isOpen())
            <span class="text-[var(--ink-2)]"><i class="fa-regular fa-calendar text-[var(--muted)] mr-1" aria-hidden="true"></i>Dự kiến bàn giao {{ $order->due_on->format('d/m/Y') }}</span>
        @endif
        @if($order->revision_rounds > 0 && $order->isOpen() && $order->rounds->isNotEmpty())
            <span class="text-[var(--ink-2)]"><i class="fa-solid fa-rotate text-[var(--muted)] mr-1" aria-hidden="true"></i>Đã sửa {{ $order->revisionsUsed() }}/{{ $order->revision_rounds }} lần</span>
        @endif
    </div>
</section>

{{-- Tiến độ --}}
@if($order->isCancelled())
    <div class="pf-card mb-8 rounded-lg border border-[var(--line)] bg-white px-5 py-4">
        <p class="font-semibold"><i class="fa-solid fa-ban text-[var(--muted)] mr-1.5" aria-hidden="true"></i>Đơn đã huỷ</p>
        @if($order->cancel_reason)<p class="text-sm text-[var(--muted)] mt-1">{{ $order->cancel_reason }}</p>@endif
    </div>
@else
    <ol class="mb-8 grid grid-cols-5 gap-1.5" aria-label="Tiến độ đơn">
        @foreach($steps as $i => $step)
            @php $state = $i < $stepIndex ? 'done' : ($i === $stepIndex ? 'current' : 'todo'); @endphp
            <li @if($state === 'current') aria-current="step" @endif>
                <span class="block h-1.5 rounded-full {{ $state === 'todo' ? 'bg-[var(--line-strong)]' : 'bg-[var(--accent)]' }} {{ $state === 'current' && $i < count($steps) - 1 ? 'bg-gradient-to-r from-[var(--accent)] from-50% to-[var(--line-strong)] to-50%' : '' }}"></span>
                <span class="block mt-2 text-[0.7rem] sm:text-xs leading-tight {{ $state === 'current' ? 'font-semibold text-[var(--ink)]' : 'text-[var(--muted)]' }}">
                    @if($state === 'done')<i class="fa-solid fa-check text-[var(--accent)] mr-0.5" aria-hidden="true"></i>@endif{{ $step['label'] }}
                </span>
            </li>
        @endforeach
    </ol>
    <p class="-mt-5 mb-8 text-sm text-[var(--ink-2)]">
        @switch($order->status)
            @case('new') {{ $studio->name }} đang xem yêu cầu của bạn và sẽ gửi báo giá. @break
            @case('quoted') Báo giá đã sẵn sàng — xem bên dưới và xác nhận để bắt đầu. @break
            @case('in_progress') {{ $studio->name }} đang thiết kế. Phương án sẽ hiện ở đây khi sẵn sàng. @break
            @case('review') Có phương án mới chờ bạn xem và phản hồi. @break
            @case('revising') {{ $studio->name }} đang chỉnh sửa theo góp ý của bạn. @break
            @case('approved') Bạn đã duyệt — {{ $studio->name }} đang chuẩn bị file bàn giao.@if($order->balance() > 0 && $order->noDeposit()) Bạn có thể thanh toán ngay ở mục bên dưới.@endif @break
            @case('delivered') Đã bàn giao. Cảm ơn bạn đã hợp tác! @break
        @endswitch
    </p>
@endif

{{-- Phương án đang chờ phản hồi --}}
@if($awaiting)
    @php
        $hasMany = $awaiting->proposals->count() > 1;
        $chosen = $awaiting->proposals->firstWhere('is_chosen', true);
        $hasImages = $awaiting->proposals->contains(fn ($p) => $p->images->isNotEmpty());
    @endphp
    <section id="phuong-an" class="mb-8 scroll-mt-6" aria-labelledby="h-await" x-data="pinboard">
        <div class="flex items-baseline gap-3 mb-1">
            <h2 id="h-await" class="font-display text-2xl font-bold tracking-tight">Phương án {{ $awaiting->label() }}</h2>
            <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 bg-[var(--accent-soft)] text-[var(--accent-ink)]">Chờ bạn phản hồi</span>
        </div>
        @if($awaiting->note)<p class="text-[var(--ink-2)] whitespace-pre-line mt-2">{{ $awaiting->note }}</p>@endif

        @if($hasImages)
            <div class="sticky top-0 z-10 -mx-4 px-4 py-2.5 mt-3 bg-[var(--paper)]/95 backdrop-blur flex flex-wrap items-center gap-2 border-b border-[var(--line)]">
                <button type="button" @click="pinMode = !pinMode" :aria-pressed="pinMode"
                        class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                        :class="pinMode ? 'bg-[var(--accent)] text-[var(--on-accent)]' : 'bg-white ring-1 ring-[var(--line-strong)] hover:ring-[var(--ink)]'">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i><span x-text="pinMode ? 'Đang ghim — chạm vào ảnh' : 'Ghim góp ý lên ảnh'">Ghim góp ý lên ảnh</span>
                </button>
                <span class="text-xs text-[var(--muted)]" x-show="pins.length" x-cloak x-text="pins.length + ' ghim'"></span>
            </div>
        @endif

        <div class="mt-4 space-y-8">
            @foreach($awaiting->proposals as $p)
                <article class="min-w-0" aria-label="{{ $p->label }}">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="font-display text-xl font-bold">{{ $p->label }}</h3>
                            @if($p->note)<p class="text-sm text-[var(--ink-2)] whitespace-pre-line mt-1">{{ $p->note }}</p>@endif
                            @if($p->drive_url)<a href="{{ $p->drive_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm text-[var(--accent-ink)] hover:underline mt-1.5"><i class="{{ link_icon($p->drive_url) }}" aria-hidden="true"></i>Xem bản lớn ({{ link_host_label($p->drive_url) }})</a>@endif
                        </div>
                        @if($hasMany)
                            @if($p->is_chosen)
                                <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-emerald-600 text-white text-sm font-semibold px-3.5 py-1.5"><i class="fa-solid fa-check" aria-hidden="true"></i>Đã chọn</span>
                            @else
                                <form method="POST" action="{{ route('public.track.choose', $order->tracking_token) }}" class="shrink-0">
                                    @csrf <input type="hidden" name="proposal_id" value="{{ $p->id }}">
                                    <x-button size="sm" variant="outline" icon="fa-hand-pointer">Chọn</x-button>
                                </form>
                            @endif
                        @endif
                    </div>
                    <div class="space-y-3">
                        @forelse($p->images as $img)
                            <div class="relative select-none crop" :class="pinMode ? 'cursor-crosshair' : ''" @click="add($event, {{ $img->id }})">
                                <img src="{{ $img->url() }}" alt="{{ $p->label }} — ảnh {{ $loop->iteration }}" style="aspect-ratio: {{ $img->aspect() }}" class="w-full rounded-md ring-1 ring-[var(--line)] object-cover bg-stone-50 pointer-events-none" loading="lazy" draggable="false">
                                <template x-for="pin in pinsFor({{ $img->id }})" :key="pin.i">
                                    <button type="button" class="absolute w-7 h-7 -ml-3.5 -mt-3.5 rounded-full text-white text-xs font-bold flex items-center justify-center ring-2 ring-white shadow-lg"
                                            :class="active === pin.i ? 'bg-[var(--ink)]' : 'bg-[var(--accent)]'"
                                            :style="`left: ${pin.x}%; top: ${pin.y}%`" @click.stop="active = pin.i; $nextTick(() => document.getElementById('pin-input-' + pin.i)?.focus())"
                                            :aria-label="'Ghim ' + (pin.i + 1)" x-text="pin.i + 1"></button>
                                </template>
                            </div>
                        @empty
                            <div class="canvas-empty rounded-md ring-1 ring-[var(--line)] aspect-[4/3] flex items-center justify-center text-sm text-[var(--muted)]">Xem file qua link phía trên</div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Phản hồi --}}
        <form method="POST" action="{{ route('public.track.respond', $order->tracking_token) }}" class="pf-card mt-8 rounded-lg border border-[var(--line)] bg-white p-5 md:p-6"
              x-data="{ decision: @js(old('decision', '')) }">
            @csrf
            <h3 class="font-display text-lg font-bold tracking-tight">Bạn thấy sao?</h3>

            <template x-if="pins.length">
                <ol class="mt-4 space-y-2.5">
                    <template x-for="(pin, i) in pins" :key="i">
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-6 h-6 shrink-0 rounded-full text-xs font-bold flex items-center justify-center" :class="active === i ? 'bg-[var(--ink)] text-white' : 'bg-[var(--accent)] text-[var(--on-accent)]'" x-text="i + 1"></span>
                            <input type="hidden" :name="`pins[${i}][image_id]`" :value="pin.image_id">
                            <input type="hidden" :name="`pins[${i}][x]`" :value="pin.x">
                            <input type="hidden" :name="`pins[${i}][y]`" :value="pin.y">
                            <label class="sr-only" :for="'pin-input-' + i" x-text="'Góp ý cho ghim ' + (i + 1)"></label>
                            <textarea :id="'pin-input-' + i" :name="`pins[${i}][body]`" x-model="pin.body" @focus="active = i" rows="1" maxlength="1000" placeholder="Chỗ này cần sửa gì?"
                                      class="flex-1 min-w-0 rounded-lg border border-[var(--line-strong)] py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)]"></textarea>
                            <button type="button" class="mt-1 w-8 h-8 rounded text-[var(--muted)] hover:text-red-600" @click="remove(i)" :aria-label="'Xoá ghim ' + (i + 1)"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </li>
                    </template>
                </ol>
            </template>

            <div class="mt-4">
                <x-textarea name="note" label="Góp ý chung" rows="3" :value="old('note')" maxlength="3000" placeholder="Cảm nhận tổng thể, điều bạn thích, điều muốn thay đổi…" />
            </div>

            @if($hasMany && ! $chosen)
                <p class="mt-3 text-sm text-amber-700"><i class="fa-solid fa-hand-pointer mr-1" aria-hidden="true"></i>Muốn duyệt? Hãy bấm <strong>Chọn</strong> ở phương án bạn ưng trước.</p>
            @endif

            <div class="mt-5 flex flex-col sm:flex-row gap-2">
                <x-button name="decision" value="approve" variant="success" icon="fa-circle-check" :disabled="$hasMany && ! $chosen"
                          onclick="return confirm({{ \Illuminate\Support\Js::from('Duyệt '.($chosen?->label ?? 'phương án này').'? Sau khi duyệt, '.$studio->name.' sẽ hoàn thiện và chuẩn bị file bàn giao.') }})">
                    Duyệt{{ $chosen ? ' '.$chosen->label : '' }}
                </x-button>
                <x-button name="decision" value="changes" variant="ink" icon="fa-rotate">Gửi góp ý để sửa</x-button>
            </div>
            @if($order->revision_rounds > 0)
                <p class="text-xs text-[var(--muted)] mt-3">
                    Đơn gồm {{ $order->revision_rounds }} lần sửa, đã dùng {{ $order->revisionsUsed() }}.
                    @if($order->revisionsLeft() <= 0) Lần sửa tiếp theo có thể phát sinh phí — {{ $studio->name }} sẽ báo bạn trước. @endif
                </p>
            @endif
        </form>
    </section>
@endif

{{-- Báo giá --}}
@if($showQuote)
    <section id="bao-gia" class="mb-8 scroll-mt-6" aria-labelledby="h-quote">
        <h2 id="h-quote" class="font-display text-2xl font-bold tracking-tight mb-3">{{ $order->quote_sent_at ? 'Báo giá' : 'Chi phí' }}</h2>
        <div class="pf-card rounded-lg border border-[var(--line)] bg-white">
            <table class="w-full text-sm">
                <caption class="sr-only">Các khoản trong báo giá</caption>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach($order->charges as $c)
                        <tr>
                            <th scope="row" class="text-left font-normal px-5 py-2.5">{{ $c->label ?: config('net.charge_kinds.'.$c->kind) }}</th>
                            <td class="text-right px-5 py-2.5 tnum whitespace-nowrap {{ $c->isDiscount() ? 'text-emerald-700' : '' }}">{{ $c->isDiscount() ? '−' : '' }}{{ money($c->amount) }}đ</td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold text-base">
                        <th scope="row" class="text-left px-5 py-3">Tổng cộng</th>
                        <td class="text-right px-5 py-3 tnum">{{ money($order->total_amount) }}đ</td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-[var(--line)] px-5 py-3 text-sm text-[var(--ink-2)] space-y-0.5">
                @if($order->depositAmount() > 0)
                    <p>Đặt cọc {{ $order->deposit_percent }}%: <span class="font-semibold tnum">{{ money($order->depositAmount()) }}đ</span> trước khi bắt đầu.</p>
                @else
                    <p>Không cần đặt cọc — bạn thanh toán một lần sau khi duyệt phương án.</p>
                @endif
                <p>Bao gồm {{ $order->revision_rounds }} lần sửa.</p>
                @if($order->due_on)<p>Dự kiến bàn giao: {{ $order->due_on->format('d/m/Y') }}.</p>@endif
            </div>
            @if($canAccept)
                <form method="POST" action="{{ route('public.track.accept', $order->tracking_token) }}" class="border-t border-[var(--line)] px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    @csrf
                    <p class="text-sm flex-1">Đồng ý với báo giá để {{ $studio->name }} lên lịch làm cho bạn.</p>
                    <x-button icon="fa-handshake">Đồng ý báo giá</x-button>
                </form>
            @elseif($order->quote_accepted_at)
                <p class="border-t border-[var(--line)] px-5 py-3 text-sm text-emerald-700"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i>Bạn đã đồng ý ngày {{ $order->quote_accepted_at->format('d/m/Y') }}.</p>
            @endif
        </div>
    </section>
@endif

{{-- Thanh toán --}}
@if((float) $order->total_amount > 0 && ! $order->isCancelled())
    <section id="thanh-toan" class="mb-8 scroll-mt-6" aria-labelledby="h-pay">
        <h2 id="h-pay" class="font-display text-2xl font-bold tracking-tight mb-3">Thanh toán</h2>
        <div class="pf-card rounded-lg border border-[var(--line)] bg-white p-5">
            @php $pct = (float) $order->total_amount > 0 ? min(100, round($order->paid_amount / $order->total_amount * 100)) : 0; @endphp
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span>Đã thanh toán <span class="font-semibold tnum">{{ money($order->paid_amount) }}đ</span> / {{ money($order->total_amount) }}đ</span>
                <span class="font-semibold {{ $order->balance() > 0 ? '' : 'text-emerald-700' }}">{{ $order->balance() > 0 ? 'Còn '.money($order->balance()).'đ' : 'Đã đủ' }}</span>
            </div>
            <div class="h-2 rounded-full bg-stone-100 mt-2" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tỉ lệ đã thanh toán"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div></div>

            @if($order->payments->isNotEmpty())
                <ul class="mt-4 text-sm divide-y divide-[var(--line)]">
                    @foreach($order->payments as $pay)
                        <li class="flex justify-between py-2"><span class="text-[var(--muted)]">{{ $pay->paid_on->format('d/m/Y') }} · {{ $pay->is_deposit ? 'Đặt cọc' : 'Thanh toán' }}</span><span class="tnum">{{ money($pay->amount) }}đ</span></li>
                    @endforeach
                </ul>
            @endif

            @if($nextAmount <= 0 && $order->balance() > 0 && $order->noDeposit() && $order->beforeApproval())
                <p class="mt-4 text-sm text-[var(--ink-2)]"><i class="fa-regular fa-clock text-[var(--muted)] mr-1.5" aria-hidden="true"></i>Không cần đặt cọc. Thông tin chuyển khoản sẽ hiện ở đây khi bạn duyệt phương án.</p>
            @endif
            @if($nextAmount > 0 && $studio->hasVietQr() && ($order->quote_accepted_at || ! in_array($order->status, ['new', 'quoted'], true) || $order->noDeposit()))
                <div class="mt-5 pt-5 border-t border-[var(--line)] flex flex-col sm:flex-row gap-5 items-center sm:items-start" x-data="{ copied: null }">
                    @if($qr)<img src="{{ $qr }}" alt="Mã VietQR chuyển {{ money($nextAmount) }} đồng" class="w-48 h-48 rounded-md ring-1 ring-[var(--line)] bg-white" loading="lazy">@endif
                    <dl class="text-sm space-y-2 w-full">
                        <div><dt class="text-[var(--muted)] text-xs">{{ ! $order->depositPaid() && $nextAmount < $order->balance() ? 'Tiền cọc' : 'Số tiền' }}</dt><dd class="font-display text-2xl font-bold tnum">{{ money($nextAmount) }}đ</dd></div>
                        <div><dt class="text-[var(--muted)] text-xs">Ngân hàng</dt><dd class="font-semibold">{{ $studio->bank_code }}</dd></div>
                        <div><dt class="text-[var(--muted)] text-xs">Số tài khoản</dt><dd class="flex items-center gap-2"><span class="font-semibold tnum">{{ $studio->bank_account }}</span>
                            <button type="button" class="text-xs text-[var(--accent-ink)] font-semibold" @click="copyText(@js($studio->bank_account)).then(() => { copied = 'acc'; setTimeout(() => copied = null, 1500) })" x-text="copied === 'acc' ? 'Đã chép' : 'Chép'">Chép</button></dd></div>
                        @if($studio->bank_holder)<div><dt class="text-[var(--muted)] text-xs">Chủ tài khoản</dt><dd class="font-semibold">{{ $studio->bank_holder }}</dd></div>@endif
                        @if($order->transferMemo() !== '')
                        <div><dt class="text-[var(--muted)] text-xs">Nội dung</dt><dd class="flex items-center gap-2"><span class="font-semibold font-mono">{{ $order->transferMemo() }}</span>
                            <button type="button" class="text-xs text-[var(--accent-ink)] font-semibold" @click="copyText(@js($order->transferMemo())).then(() => { copied = 'memo'; setTimeout(() => copied = null, 1500) })" x-text="copied === 'memo' ? 'Đã chép' : 'Chép'">Chép</button></dd></div>
                        @endif
                    </dl>
                </div>
                <p class="text-xs text-[var(--muted)] mt-4">Sau khi chuyển, {{ $studio->name }} sẽ xác nhận và cập nhật ở đây.</p>
            @endif
        </div>
    </section>
@endif

{{-- Bàn giao --}}
@if($sources->isNotEmpty() || $order->isDelivered())
    <section id="ban-giao" class="mb-8 scroll-mt-6" aria-labelledby="h-deliver">
        <h2 id="h-deliver" class="font-display text-2xl font-bold tracking-tight mb-3">File bàn giao</h2>
        <div class="pf-card rounded-lg border {{ $order->deliveryUnlocked() ? 'border-emerald-200' : 'border-[var(--line)]' }} bg-white p-5">
            @if(! $order->deliveryUnlocked())
                <p class="font-semibold"><i class="fa-solid fa-lock text-[var(--muted)] mr-1.5" aria-hidden="true"></i>File đã sẵn sàng</p>
                <p class="text-sm text-[var(--muted)] mt-1">Link tải sẽ mở ngay khi thanh toán đủ (còn {{ money($order->balance()) }}đ).</p>
            @elseif($sources->isEmpty())
                <p class="text-sm text-[var(--muted)]">{{ $studio->name }} sẽ gửi link file tại đây.</p>
            @else
                <ul class="space-y-2">
                    @foreach($sources as $link)
                        <li><a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-lg border border-[var(--line)] px-4 py-3 hover:border-[var(--ink)]">
                            <i class="{{ link_icon($link->url) }} text-lg text-[var(--accent)]" aria-hidden="true"></i>
                            <span class="flex-1 min-w-0"><span class="block font-semibold truncate">{{ $link->displayLabel() }}</span><span class="block text-xs text-[var(--muted)] truncate">{{ link_host_label($link->url) }}</span></span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-xs text-[var(--muted)]" aria-hidden="true"></i>
                        </a></li>
                    @endforeach
                </ul>
                <p class="text-xs text-[var(--muted)] mt-3">Hãy tải về và lưu trữ — link có thể không còn mãi.</p>
            @endif
        </div>
    </section>
@endif

{{-- Các vòng trước --}}
@if($pastRounds->isNotEmpty())
    <section class="mb-8" aria-labelledby="h-past">
        <h2 id="h-past" class="font-display text-2xl font-bold tracking-tight mb-3">{{ $awaiting ? 'Các vòng trước' : 'Phương án' }}</h2>
        <div class="space-y-3">
            @foreach($pastRounds as $round)
                @include('public._round-past', ['round' => $round])
            @endforeach
        </div>
    </section>
@endif

{{-- Trao đổi --}}
<section id="trao-doi" class="mb-8 scroll-mt-6" aria-labelledby="h-thread">
    <h2 id="h-thread" class="font-display text-2xl font-bold tracking-tight mb-3">Trao đổi</h2>
    <div class="pf-card rounded-lg border border-[var(--line)] bg-white">
        @if($order->feedback->isNotEmpty())
            <ol class="p-5 space-y-3">
                @foreach($order->feedback as $msg)
                    <li class="flex {{ $msg->fromClient() ? 'justify-end' : '' }}">
                        <div class="max-w-[85%] rounded-lg px-3.5 py-2.5 text-sm {{ $msg->fromClient() ? 'bg-[var(--accent-soft)]' : 'bg-stone-100' }}">
                            <p class="text-xs font-semibold mb-0.5 {{ $msg->fromClient() ? 'text-[var(--accent-ink)]' : 'text-[var(--ink-2)]' }}">{{ $msg->fromClient() ? 'Bạn' : $studio->name }} <span class="font-normal text-[var(--muted)]">· {{ $msg->created_at->format('H:i d/m') }}</span></p>
                            <p class="whitespace-pre-line break-words">{{ $msg->body }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
        @unless($order->isCancelled())
            <form method="POST" action="{{ route('public.track.message', $order->tracking_token) }}" class="{{ $order->feedback->isNotEmpty() ? 'border-t border-[var(--line)]' : '' }} p-4 flex gap-2 items-end">
                @csrf
                <label for="body" class="sr-only">Lời nhắn</label>
                <textarea id="body" name="body" rows="2" required maxlength="2000" placeholder="Nhắn cho {{ $studio->name }}…" class="flex-1 min-w-0 rounded-lg border border-[var(--line-strong)] py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)]">{{ old('body') }}</textarea>
                <x-button size="sm" variant="ink" icon="fa-paper-plane" aria-label="Gửi lời nhắn"><span class="hidden sm:inline">Gửi</span></x-button>
            </form>
        @endunless
    </div>
</section>

{{-- Yêu cầu đã gửi --}}
<section class="mb-8" aria-labelledby="h-brief">
    <details class="pf-card rounded-lg border border-[var(--line)] bg-white group">
        <summary class="cursor-pointer list-none px-5 py-4 flex items-center gap-2">
            <h2 id="h-brief" class="font-semibold">Yêu cầu của bạn</h2>
            <i class="fa-solid fa-chevron-down text-xs text-[var(--muted)] ml-auto transition group-open:rotate-180" aria-hidden="true"></i>
        </summary>
        <dl class="px-5 pb-5 grid gap-3 text-sm">
            @if($order->service)<div><dt class="text-xs text-[var(--muted)]">Dịch vụ</dt><dd>{{ $order->service->name }}</dd></div>@endif
            @if($order->brief)<div><dt class="text-xs text-[var(--muted)]">Mô tả</dt><dd class="whitespace-pre-line">{{ $order->brief }}</dd></div>@endif
            @foreach(['style' => 'Phong cách', 'colors' => 'Màu sắc', 'specs' => 'Kích thước / định dạng', 'copy_text' => 'Nội dung chữ', 'budget_note' => 'Ngân sách dự kiến'] as $field => $label)
                @if($order->{$field})<div><dt class="text-xs text-[var(--muted)]">{{ $label }}</dt><dd class="whitespace-pre-line">{{ $order->{$field} }}</dd></div>@endif
            @endforeach
            @foreach($order->briefAnswers() as $a)
                <div><dt class="text-xs text-[var(--muted)]">{{ $a['question'] }}</dt><dd class="whitespace-pre-line">{{ $a['answer'] }}</dd></div>
            @endforeach
            @if($order->client_due_on)<div><dt class="text-xs text-[var(--muted)]">Ngày bạn cần</dt><dd>{{ $order->client_due_on->format('d/m/Y') }}</dd></div>@endif
            @if($references->isNotEmpty())
                <div><dt class="text-xs text-[var(--muted)]">Tài liệu</dt>
                    <dd class="mt-1 space-y-1">@foreach($references as $link)<a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer nofollow" class="flex items-center gap-2 text-[var(--accent-ink)] hover:underline break-all"><i class="{{ link_icon($link->url) }}" aria-hidden="true"></i>{{ $link->displayLabel() }}</a>@endforeach</dd></div>
            @endif
        </dl>
    </details>
</section>

{{-- Dòng thời gian --}}
@if($order->events->isNotEmpty())
    <section class="mb-4" aria-labelledby="h-time">
        <h2 id="h-time" class="text-sm font-semibold uppercase tracking-wider text-[var(--muted)] mb-3">Lịch sử</h2>
        <ol class="relative border-l border-[var(--line-strong)] ml-2 space-y-3">
            @foreach($order->events->take(30) as $e)
                <li class="pl-5 relative">
                    <span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full {{ $loop->first ? 'bg-[var(--accent)]' : 'bg-[var(--line-strong)]' }}" aria-hidden="true"></span>
                    <p class="text-sm">{{ $e->actor === 'client' ? preg_replace('/^Khách /u', 'Bạn ', $e->message) : $e->message }}</p>
                    <p class="text-xs text-[var(--muted)]">{{ $e->created_at->format('H:i · d/m/Y') }}</p>
                </li>
            @endforeach
        </ol>
    </section>
@endif
@endsection
