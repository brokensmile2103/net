@extends('layouts.app')
@section('title', $order->code.' · '.$order->title.' · Nét')
@section('page-title', $order->code.' · '.$order->title)
@section('actions')
    <x-button variant="outline" size="sm" icon="fa-arrow-up-right-from-square" :href="$order->trackingUrl()" target="_blank" rel="noopener">Trang của khách</x-button>
    <x-button variant="outline" size="sm" icon="fa-pen" :href="route('orders.edit', $order->id)" data-shortcut="edit" title="Sửa đơn (phím E)">Sửa đơn</x-button>
@endsection
@section('content')
@php
    $statuses = config('net.statuses');
    $canNewRound = ! $order->isCancelled() && ! $draft && ! $order->rounds->contains('status', 'sent');
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-6xl mx-auto">
    <x-errors class="mb-5" />

    {{-- Đầu trang đơn --}}
    <div class="flex flex-col sm:flex-row gap-5 sm:items-start mb-7">
        <x-cover :order="$order" class="w-full sm:w-44 shrink-0" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 text-sm text-[var(--muted)]">
                <a href="{{ route('orders.index') }}" class="hover:text-[var(--ink)]">Đơn</a><span aria-hidden="true">/</span><span class="font-semibold text-[var(--ink-2)]">{{ $order->code }}</span>
                @if($order->source === 'form')<span class="text-xs rounded px-1.5 py-0.5 bg-stone-100">khách tự đặt</span>@endif
                @if($order->isUrgent())<span class="text-xs rounded px-1.5 py-0.5 bg-red-50 text-red-700 font-semibold">Gấp</span>@endif
            </div>
            <h2 class="font-display text-2xl md:text-3xl font-bold tracking-tight mt-1 md:hidden">{{ $order->title }}</h2>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <x-status :order="$order" />
                @if($order->dueLabel())
                    <span class="{{ $order->isOverdue() ? 'text-red-600 font-semibold' : 'text-[var(--ink-2)]' }}"><i class="fa-regular fa-calendar mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>{{ $order->dueLabel() }} ({{ $order->due_on->format('d/m/Y') }})</span>
                @else
                    <a href="{{ route('orders.edit', $order->id) }}" class="text-[var(--muted)] hover:text-[var(--ink)]"><i class="fa-regular fa-calendar mr-1.5" aria-hidden="true"></i>Chưa đặt hạn</a>
                @endif
                <a href="{{ route('clients.show', $order->client_id) }}" class="text-[var(--ink-2)] hover:text-[var(--ink)]"><i class="fa-regular fa-user mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>{{ $order->client?->name }}</a>
                @if($order->client?->messengerUrl())
                    <a href="{{ $order->client->messengerUrl() }}" target="_blank" rel="noopener nofollow" class="text-[var(--ink-2)] hover:text-[var(--ink)]"><i class="fa-brands fa-facebook-messenger mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>Messenger</a>
                @elseif($order->client?->phone)
                    <a href="https://zalo.me/{{ preg_replace('/\D/', '', $order->client->phone) }}" target="_blank" rel="noopener" class="text-[var(--ink-2)] hover:text-[var(--ink)]"><i class="fa-solid fa-comment mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>Zalo</a>
                @endif
                <span class="text-[var(--ink-2)]"><i class="fa-solid fa-rotate mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>Đã sửa {{ $order->revisionsUsed() }}/{{ $order->revision_rounds }} lần</span>
                <span class="text-[var(--ink-2)]"><i class="fa-solid fa-wallet mr-1.5 text-[var(--muted)]" aria-hidden="true"></i>{{ $order->paymentLabel() }}</span>
            </div>

            <form method="POST" action="{{ route('orders.status', $order->id) }}" class="mt-4 flex flex-wrap items-center gap-2" x-data="{ s: @js($order->status) }"
                  @submit="if (s === 'cancelled') { const r = prompt('Lý do huỷ đơn (khách sẽ thấy):'); if (r === null) { $event.preventDefault(); return; } $refs.reason.value = r; }">
                @csrf @method('PATCH')
                <label for="status-select" class="text-sm text-[var(--muted)]">Giai đoạn</label>
                <select id="status-select" name="status" x-model="s" class="rounded-md border border-[var(--line-strong)] bg-white py-1.5 px-2.5 text-sm">
                    @foreach($statuses as $k => $st)<option value="{{ $k }}">{{ $st['label'] }}</option>@endforeach
                </select>
                <input type="hidden" name="reason" x-ref="reason">
                <button x-show="s !== @js($order->status)" x-cloak class="rounded-md bg-[var(--ink)] text-white text-sm font-semibold px-3 py-1.5">Chuyển</button>
                <a href="{{ route('orders.edit', $order->id) }}" class="md:hidden ml-auto text-sm text-[var(--accent-ink)] font-semibold">Sửa đơn</a>
            </form>
        </div>
    </div>

    {{-- Đơn mới: nhận làm ngay (quy trình đơn giản) hoặc báo giá trước --}}
    @if(in_array($order->status, ['new', 'quoted'], true))
        <section class="mb-7 rounded-lg border-2 border-[var(--accent)] bg-white p-5" aria-labelledby="h-start">
            <h3 id="h-start" class="font-display text-lg font-bold tracking-tight">{{ $order->status === 'new' ? 'Đơn mới — bắt đầu thế nào?' : 'Đang chờ khách đồng ý báo giá' }}</h3>
            <div class="mt-3 grid md:grid-cols-2 gap-4">
                <form method="POST" action="{{ route('orders.accept', $order->id) }}" class="rounded-md bg-stone-50 border border-[var(--line)] p-4 flex flex-col gap-3">
                    @csrf
                    <p class="text-sm"><span class="font-semibold">Nhận đơn & làm luôn.</span>
                        <span class="text-[var(--muted)]">
                            @if((float) $order->total_amount > 0) Giá {{ money($order->total_amount) }}đ theo bảng giá. @else Chưa có giá — bạn có thể nhập ở mục Báo giá sau. @endif
                            Không cần khách bấm đồng ý.
                        </span>
                    </p>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="no_deposit" value="1" class="mt-0.5 accent-[var(--accent)]" @checked($order->noDeposit())>
                        <span>Không cần cọc — khách thanh toán một lần sau khi duyệt</span>
                    </label>
                    <x-button size="sm" icon="fa-play" class="mt-auto self-start">Nhận đơn & bắt đầu</x-button>
                </form>
                <div class="rounded-md border border-[var(--line)] p-4 flex flex-col gap-3">
                    <p class="text-sm"><span class="font-semibold">Báo giá trước.</span> <span class="text-[var(--muted)]">Gửi báo giá{{ $order->noDeposit() ? '' : ' kèm % cọc' }}, khách bấm “Đồng ý báo giá” trên trang theo dõi rồi bạn mới bắt đầu.</span></p>
                    @if($order->quote_sent_at)
                        <p class="text-xs text-[var(--muted)]">Đã gửi báo giá {{ $order->quote_sent_at->format('H:i d/m') }}{{ $order->quote_accepted_at ? ' · khách đã đồng ý' : ' · chờ khách đồng ý' }}.</p>
                    @endif
                    <x-button size="sm" variant="outline" icon="fa-file-signature" href="#bao-gia" class="mt-auto self-start">{{ $order->quote_sent_at ? 'Xem báo giá' : 'Soạn báo giá' }}</x-button>
                </div>
            </div>
        </section>
    @endif

    <div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-6 lg:gap-8 items-start">
        {{-- CỘT CHÍNH --}}
        <div class="space-y-6 min-w-0">
            @include('orders._brief')

            {{-- PHƯƠNG ÁN --}}
            <section aria-labelledby="h-phuong-an">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h3 id="h-phuong-an" class="font-display text-xl font-bold tracking-tight">Phương án</h3>
                    @if($canNewRound)
                        <form method="POST" action="{{ route('rounds.store', $order->id) }}">@csrf
                            <x-button size="sm" icon="fa-plus">{{ $order->rounds->isEmpty() ? 'Tạo vòng V1' : 'Tạo vòng V'.($order->rounds->max('number') + 1) }}</x-button>
                        </form>
                    @endif
                </div>

                @forelse($order->rounds->sortByDesc('number') as $round)
                    @include('orders._round', ['round' => $round])
                @empty
                    <div class="rounded-lg border border-dashed border-[var(--line-strong)] bg-white px-5 py-8 text-center">
                        <p class="font-semibold">Chưa có phương án nào</p>
                        <p class="text-sm text-[var(--muted)] mt-1 max-w-md mx-auto">Tạo vòng V1, thêm các phương án (ảnh xem trước + link file trên Drive) rồi bấm "Gửi cho khách". Khách chọn, ghim góp ý lên hình và duyệt ngay trên trang theo dõi.</p>
                    </div>
                @endforelse
            </section>

            {{-- TRAO ĐỔI --}}
            <section id="trao-doi" aria-labelledby="h-trao-doi" class="scroll-mt-20">
                <h3 id="h-trao-doi" class="font-display text-xl font-bold tracking-tight mb-3">Trao đổi với khách</h3>
                <div class="rounded-lg border border-[var(--line)] bg-white">
                    @forelse($thread as $fb)
                        <div class="px-4 py-3 border-b border-[var(--line)] last:border-0 flex gap-3 {{ $fb->fromClient() ? '' : 'bg-stone-50/60' }}">
                            <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center text-[0.65rem] font-bold {{ $fb->fromClient() ? 'bg-[var(--accent-soft)] text-[var(--accent-ink)]' : 'bg-[var(--ink)] text-white' }}">{{ $fb->fromClient() ? $order->client?->initials() : 'Tôi' }}</span>
                            <div class="min-w-0">
                                <p class="text-xs text-[var(--muted)]">{{ $fb->fromClient() ? $order->client?->name : 'Bạn' }} · {{ $fb->created_at->format('H:i d/m') }}</p>
                                <p class="text-sm whitespace-pre-line mt-0.5">{{ $fb->body }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-5 text-sm text-[var(--muted)]">Chưa có lời nhắn nào. Lời nhắn ở đây hiện trên trang theo dõi của khách — giữ trao đổi về đơn ở một chỗ.</p>
                    @endforelse
                    @unless($order->isCancelled())
                        <form method="POST" action="{{ route('orders.message', $order->id) }}" class="p-3 border-t border-[var(--line)] flex gap-2 items-end">
                            @csrf
                            <label for="msg" class="sr-only">Nhắn khách</label>
                            <textarea id="msg" name="body" rows="1" required maxlength="2000" placeholder="Nhắn khách…" class="flex-1 rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--accent)]"></textarea>
                            <x-button size="sm" variant="ink" icon="fa-paper-plane">Gửi</x-button>
                        </form>
                    @endunless
                </div>
            </section>

            {{-- DÒNG THỜI GIAN --}}
            <section aria-labelledby="h-timeline">
                <h3 id="h-timeline" class="font-display text-xl font-bold tracking-tight mb-3">Dòng thời gian</h3>
                <ol class="relative border-l border-[var(--line-strong)] ml-2 space-y-4">
                    @foreach($order->events as $e)
                        <li class="ml-5">
                            <span class="absolute -left-[9px] w-[17px] h-[17px] rounded-full bg-white border border-[var(--line-strong)] flex items-center justify-center text-[0.5rem] {{ $e->actor === 'client' ? 'text-[var(--accent)]' : 'text-[var(--muted)]' }}"><i class="fa-solid {{ $e->icon() }}" aria-hidden="true"></i></span>
                            <p class="text-sm {{ $e->actor === 'client' ? 'font-semibold' : '' }}">{{ $e->message }}</p>
                            <p class="text-xs text-[var(--muted)]">{{ $e->created_at->format('H:i d/m/Y') }}@if(! $e->visible) · chỉ bạn thấy @endif</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- CỘT PHỤ --}}
        <aside class="space-y-5">
            @include('orders._side')
        </aside>
    </div>
</div>
@endsection
