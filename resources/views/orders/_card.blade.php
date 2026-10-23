{{--
    1 thẻ đơn trên Bảng việc. Cùng một khối HTML cho cả 3 chế độ xem — CSS (app.css, [data-view]) sắp lại:
      cards   — ảnh bìa lớn phía trên;
      compact — bìa thu nhỏ nằm cùng hàng với khối mã đơn + tên dự án + khách;
      rows    — mỗi đơn 1 dòng, không ảnh.
    Biến: $order (đã có client, has_source_link). Dùng ở orders/board và khi "Tải thêm" (OrderController@boardMore).
--}}
@php
    $stalled = $order->isStalled();
    $days = $order->daysInStatus();
    $payDot = ['paid' => 'bg-emerald-500', 'deposit' => 'bg-sky-500', 'partial' => 'bg-amber-500'][$order->paymentStatus()] ?? 'bg-stone-300';
@endphp
<article id="card-{{ $order->id }}" data-card data-id="{{ $order->id }}" data-code="{{ $order->code }}" data-status="{{ $order->status }}"
         data-amount="{{ (float) $order->total_amount }}" data-can-deliver="{{ $order->has_source_link ? '1' : '0' }}" data-url="{{ route('orders.show', $order->id) }}"
         tabindex="0" aria-roledescription="thẻ kéo được" aria-label="{{ $order->code }} · {{ $order->title }}{{ $order->client ? ' · '.$order->client->name : '' }}"
         @keydown="cardKey($event, {{ $order->id }})"
         class="board-card group {{ $order->coverUrl() ? 'has-cover' : '' }} {{ $order->isUrgent() ? 'is-urgent' : '' }}"
         :class="selected.includes({{ $order->id }}) && 'is-selected'"
         x-data="{ menu: false }">
    <label class="board-check" for="pick-{{ $order->id }}" data-no-drag title="Chọn đơn (phím X)">
        <input type="checkbox" id="pick-{{ $order->id }}" class="accent-[var(--accent)]" :checked="selected.includes({{ $order->id }})" @change="toggleSelect({{ $order->id }})" tabindex="-1" aria-label="Chọn đơn {{ $order->code }}">
    </label>

    @if($order->coverUrl())
        <img src="{{ $order->coverUrl() }}" alt="" class="board-cover" loading="lazy" draggable="false" decoding="async">
    @else
        <span class="board-cover board-cover-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($order->title, 0, 1)) }}</span>
    @endif

    <a href="{{ route('orders.show', $order->id) }}" class="board-main" draggable="false" tabindex="-1">
        <span class="board-code tnum">{{ $order->code }}@if($order->isUrgent())<span class="board-urgent">Gấp</span>@endif</span>
        <span class="board-title">{{ $order->title }}</span>
        @if($order->client)<span class="board-client">{{ $order->client->name }}</span>@endif
    </a>

    <div class="board-menu relative" data-no-drag>
        <button type="button" class="w-7 h-7 rounded hover:bg-stone-100 text-[var(--muted)] text-xs" @click="menu = !menu" :aria-expanded="menu.toString()" aria-label="Chuyển giai đoạn đơn {{ $order->code }}" tabindex="-1"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i></button>
        <div x-show="menu" x-cloak x-transition.origin.top.right @click.outside="menu = false" @keydown.escape.stop="menu = false" class="absolute right-0 top-8 z-20 w-48 bg-white rounded-md shadow-lg ring-1 ring-[var(--line)] py-1">
            <p class="px-3 pt-1 pb-1.5 text-[0.7rem] text-[var(--muted)]">Chuyển sang</p>
            @foreach(config('net.statuses') as $k => $st)
                @continue($k === $order->status)
                <button type="button" class="w-full text-left px-3 py-1.5 text-sm hover:bg-stone-50 flex items-center gap-2 {{ $k === 'cancelled' ? 'text-red-600' : '' }}" @click="menu = false; move({{ $order->id }}, @js($k))">
                    <span class="w-1.5 h-1.5 rounded-full {{ tone_dot($st['tone']) }}" aria-hidden="true"></span>{{ $st['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="board-meta">
        @if($order->dueLabel())
            <span class="{{ $order->isOverdue() ? 'text-red-600 font-semibold' : '' }}" title="Hạn bàn giao {{ $order->due_on->format('d/m/Y') }}"><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $order->dueLabel() }}</span>
        @endif
        @if($stalled)
            <span class="board-stalled" title="Nằm ở “{{ $order->statusLabel() }}” {{ $days }} ngày"><i class="fa-regular fa-hourglass-half mr-1" aria-hidden="true"></i>{{ $days }} ngày</span>
        @endif
        @if($order->has_source_link && $order->status !== 'delivered')
            <span class="text-emerald-700" title="Đã có link file bàn giao"><i class="fa-solid fa-box-archive" aria-hidden="true"></i><span class="sr-only">Đã có link bàn giao</span></span>
        @endif
        @if((float) $order->total_amount > 0)
            <span class="board-amount tnum" title="{{ $order->paymentLabel() }}"><i class="inline-block w-1.5 h-1.5 rounded-full align-middle mr-1 {{ $payDot }}" aria-hidden="true"></i>{{ money_compact($order->total_amount) }}</span>
        @endif
    </div>
</article>
