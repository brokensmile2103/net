@extends('layouts.app')
@section('title', 'Bảng việc · Nét')
@section('page-title', 'Bảng việc')
@section('actions')
    <x-button size="sm" icon="fa-plus" :href="route('orders.create')" title="Đơn mới (Alt + N)">Đơn mới</x-button>
@endsection
@section('content')
@php
    $boardLabels = [
        'statuses' => collect(config('net.statuses'))->map(fn ($s) => $s['label']),
        'needSource' => 'Thêm link file bàn giao (Google Drive…) trong trang đơn trước khi chuyển sang “Đã bàn giao”.',
        'cancelPrompt' => 'Lý do huỷ đơn (khách sẽ thấy):',
    ];
    $loaded = collect($cards)->map->count();
    $boardConfig = [
        'prefsUrl' => route('orders.board.prefs'),
        'moreUrl' => route('orders.board.more'),
        'query' => $board->queryParams(),
        'view' => $prefs['view'],
        'hidden' => $prefs['hidden'],
        'collapsed' => $prefs['collapsed'],
        'limits' => (object) $prefs['limits'],
        'counts' => $columns->map(fn ($c, $k) => ['count' => $totals[$k]['count'] ?? 0, 'sum' => $totals[$k]['sum'] ?? 0]),
        'remaining' => $columns->map(fn ($c, $k) => max(0, ($totals[$k]['count'] ?? 0) - $loaded[$k])),
    ];
    $matched = collect($totals)->sum('count');
    $filters = config('net.board.filters');
    $active = $board->criteria['filters'];
@endphp
<div class="py-4 md:py-5" x-data="board(@js(route('orders.status', ['order' => '__ID__'])), @js($boardLabels), @js($boardConfig))"
     data-view="{{ $prefs['view'] }}" :data-view="view" :class="selected.length && 'is-selecting'" @dragstart.prevent @contextmenu="drag && $event.preventDefault()">

    {{-- Thanh công cụ: tìm, lọc nhanh, sắp xếp (gửi lên máy chủ) · chế độ xem, cột (lưu ngay) --}}
    <div class="px-4 md:px-8 mb-3 space-y-2.5">
        <form method="GET" action="{{ route('orders.board') }}" class="flex flex-wrap items-center gap-2" x-ref="filters"
              @submit="[...$el.elements].forEach((i) => { if (i.name && i.value === '') i.disabled = true })">
            <div class="relative flex-1 min-w-[14rem] max-w-md">
                <label for="board-q" class="sr-only">Tìm trên Bảng việc</label>
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm" aria-hidden="true"></i>
                <input id="board-q" name="q" value="{{ $board->criteria['q'] }}" data-search-focus placeholder="Tìm mã đơn, dự án, khách…" autocomplete="off"
                       class="w-full rounded-lg border border-[var(--line-strong)] bg-white pl-9 pr-9 py-2 text-sm" @keydown.escape="$el.value = ''; $el.blur()">
                <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 kbd-hint">/</kbd>
            </div>
            @if($services->isNotEmpty())
                <label for="board-dv" class="sr-only">Dịch vụ</label>
                <select id="board-dv" name="dv" class="rounded-lg border border-[var(--line-strong)] bg-white py-2 pl-3 pr-8 text-sm" @change="$el.form.requestSubmit()">
                    <option value="">Mọi dịch vụ</option>
                    @foreach($services as $s)<option value="{{ $s->id }}" @selected($board->criteria['service'] === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            @endif
            <label for="board-sort" class="sr-only">Sắp xếp thẻ trong cột</label>
            <select id="board-sort" name="sap-xep" class="rounded-lg border border-[var(--line-strong)] bg-white py-2 pl-3 pr-8 text-sm" @change="$el.form.requestSubmit()" title="Sắp xếp thẻ trong cột">
                @foreach(config('net.board.sorts') as $k => $label)<option value="{{ $k }}" @selected($prefs['sort'] === $k)>{{ $label }}</option>@endforeach
            </select>

            <div class="ml-auto flex items-center gap-2">
                {{-- Chế độ xem --}}
                <div class="inline-flex rounded-lg border border-[var(--line-strong)] bg-white p-0.5" role="radiogroup" aria-label="Chế độ xem">
                    @foreach(config('net.board.views') as $k => $v)
                        <button type="button" role="radio" :aria-checked="(view === @js($k)).toString()" @click="setView(@js($k))"
                                class="px-2.5 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5"
                                :class="view === @js($k) ? 'bg-[var(--ink)] text-white' : 'text-[var(--ink-2)] hover:bg-stone-100'"
                                title="{{ $v['hint'] }} (phím {{ $loop->iteration }})">
                            <i class="fa-solid {{ $v['icon'] }}" aria-hidden="true"></i><span class="hidden sm:inline">{{ $v['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                {{-- Cột hiển thị --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" class="rounded-lg border border-[var(--line-strong)] bg-white px-3 py-2 text-sm font-semibold inline-flex items-center gap-2" @click="open = !open" :aria-expanded="open.toString()">
                        <i class="fa-solid fa-table-columns text-[var(--muted)]" aria-hidden="true"></i>Cột
                        <span class="text-xs font-normal text-[var(--muted)]" x-show="hidden.length" x-cloak x-text="'(ẩn ' + hidden.length + ')'"></span>
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false" @keydown.escape.stop="open = false"
                         class="absolute right-0 top-11 z-30 w-64 rounded-lg bg-white shadow-lg ring-1 ring-[var(--line)] p-2">
                        <p class="px-2 pt-1 pb-2 text-xs text-[var(--muted)]">Hiện cột</p>
                        @foreach($columns as $key => $col)
                            <label for="board-col-{{ $key }}" class="flex items-center gap-2.5 px-2 py-1.5 rounded-md hover:bg-stone-50 text-sm cursor-pointer">
                                <input type="checkbox" id="board-col-{{ $key }}" class="accent-[var(--accent)]" :checked="!hidden.includes(@js($key))" @change="toggleHidden(@js($key))">
                                <span class="w-2 h-2 rounded-full {{ tone_dot($col['tone']) }}" aria-hidden="true"></span>
                                <span class="flex-1">{{ $col['label'] }}</span>
                                <span class="text-xs text-[var(--muted)] tnum" x-text="counts[@js($key)].count"></span>
                            </label>
                        @endforeach
                        <div class="border-t border-[var(--line)] mt-2 pt-2 px-2">
                            <label for="board-days" class="block text-xs text-[var(--muted)] mb-1">Cột “Đã bàn giao” hiện đơn trong</label>
                            <select id="board-days" class="w-full rounded-md border border-[var(--line-strong)] py-1.5 px-2 text-sm" @change="savePrefs({ delivered_days: +$el.value }).then(() => location.reload())">
                                @foreach(config('net.board.delivered_days') as $d)<option value="{{ $d }}" @selected($prefs['delivered_days'] === $d)>{{ $d }} ngày gần đây</option>@endforeach
                            </select>
                        </div>
                        <button type="button" class="mt-2 w-full text-left px-2 py-1.5 rounded-md text-sm text-[var(--accent-ink)] hover:bg-stone-50" x-show="hidden.length || collapsed.length" @click="showAll(); open = false">Hiện lại tất cả cột</button>
                    </div>
                </div>
            </div>

            {{-- Lọc nhanh --}}
            <div class="basis-full flex flex-wrap items-center gap-1.5" role="group" aria-label="Lọc nhanh">
                @foreach($filters as $k => $label)
                    <label class="board-chip {{ in_array($k, $active, true) ? 'is-on' : '' }}">
                        <input type="checkbox" name="loc[]" value="{{ $k }}" class="sr-only" @checked(in_array($k, $active, true)) @change="$el.form.requestSubmit()">{{ $label }}
                    </label>
                @endforeach
                @if($board->isFiltered())
                    <span class="text-xs text-[var(--muted)] ml-1 tnum">{{ $matched }} đơn khớp</span>
                    <a href="{{ route('orders.board') }}" class="text-xs font-semibold text-[var(--accent-ink)] hover:underline ml-1">Xoá lọc</a>
                @endif
                <button type="button" class="ml-auto hidden md:inline text-xs text-[var(--muted)] hover:text-[var(--ink)]" @click="$dispatch('net-shortcuts')"><i class="fa-regular fa-keyboard mr-1" aria-hidden="true"></i>Phím tắt <kbd class="kbd-hint">?</kbd></button>
            </div>
            <noscript><button class="rounded-lg bg-[var(--ink)] text-white px-3 py-2 text-sm">Lọc</button></noscript>
        </form>
    </div>

    <div x-ref="scroller" class="board-scroller flex gap-3 overflow-x-auto px-4 md:px-8 pb-6 md:snap-none cursor-grab" :class="drag ? 'snap-none' : 'snap-x'" style="scroll-padding: 1rem" @pointerdown="pointerDown($event)">
        @foreach($columns as $key => $col)
            @php $list = $cards[$key]; @endphp
            <section data-column="{{ $key }}" class="board-col snap-start shrink-0 rounded-lg bg-stone-100/80 flex flex-col max-h-[calc(100dvh-15rem)] transition-[box-shadow,background-color,width]"
                     x-show="!hidden.includes(@js($key))" @if(in_array($key, $prefs['hidden'], true)) style="display: none" @endif
                     :class="[collapsed.includes(@js($key)) && 'is-collapsed', overLimit(@js($key)) && 'is-over-limit',
                              over === @js($key) ? (blocked ? 'ring-2 ring-red-400 bg-red-50/70' : 'ring-2 ring-[var(--accent)] bg-[var(--accent-soft)]/60') : '']"
                     aria-label="{{ $col['label'] }}" x-data="{ colMenu: false }">
                <header class="board-col-head px-3 pt-2.5 pb-2 flex items-center gap-2">
                    <button type="button" class="board-col-toggle" @click="toggleCollapsed(@js($key))" :aria-expanded="(!collapsed.includes(@js($key))).toString()"
                            :title="collapsed.includes(@js($key)) ? 'Mở rộng cột' : 'Thu gọn cột'">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ tone_dot($col['tone']) }}" aria-hidden="true"></span>
                        <h2 class="board-col-title text-sm font-semibold whitespace-nowrap">{{ $col['label'] }}</h2>
                        <span class="board-col-count text-xs text-[var(--muted)] tnum" :title="limits[@js($key)] ? 'Giới hạn ' + limits[@js($key)] + ' đơn' : 'Số đơn'">
                            <span x-text="counts[@js($key)].count">{{ $totals[$key]['count'] ?? 0 }}</span><span x-show="limits[@js($key)]" x-cloak x-text="'/' + limits[@js($key)]"></span>
                        </span>
                    </button>
                    <span class="board-col-sum ml-auto text-xs text-[var(--muted)] tnum" title="Tổng báo giá các đơn trong cột" x-show="counts[@js($key)].sum > 0" x-text="money(counts[@js($key)].sum)">{{ ($totals[$key]['sum'] ?? 0) > 0 ? money_compact($totals[$key]['sum']) : '' }}</span>
                    <div class="board-col-actions relative" data-no-drag>
                        <button type="button" class="w-6 h-6 rounded hover:bg-stone-200 text-[var(--muted)] text-xs" @click="colMenu = !colMenu" :aria-expanded="colMenu.toString()" aria-label="Tuỳ chọn cột {{ $col['label'] }}"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                        <div x-show="colMenu" x-cloak x-transition.origin.top.right @click.outside="colMenu = false" @keydown.escape.stop="colMenu = false" class="absolute right-0 top-7 z-30 w-56 bg-white rounded-md shadow-lg ring-1 ring-[var(--line)] py-1 text-sm">
                            <button type="button" class="w-full text-left px-3 py-1.5 hover:bg-stone-50" @click="colMenu = false; toggleCollapsed(@js($key))"><i class="fa-solid fa-down-left-and-up-right-to-center w-4 mr-2 text-[var(--muted)]" aria-hidden="true"></i>Thu gọn cột</button>
                            <button type="button" class="w-full text-left px-3 py-1.5 hover:bg-stone-50" @click="colMenu = false; toggleHidden(@js($key))"><i class="fa-regular fa-eye-slash w-4 mr-2 text-[var(--muted)]" aria-hidden="true"></i>Ẩn cột</button>
                            <button type="button" class="w-full text-left px-3 py-1.5 hover:bg-stone-50" @click="colMenu = false; setLimit(@js($key), @js($col['label']))"><i class="fa-solid fa-gauge-high w-4 mr-2 text-[var(--muted)]" aria-hidden="true"></i>Giới hạn số đơn…</button>
                            <button type="button" class="w-full text-left px-3 py-1.5 hover:bg-stone-50" @click="colMenu = false; selectColumn(@js($key))"><i class="fa-regular fa-square-check w-4 mr-2 text-[var(--muted)]" aria-hidden="true"></i>Chọn các thẻ đang hiện</button>
                        </div>
                    </div>
                </header>
                <p class="board-limit-note px-3 -mt-1 pb-1.5 text-[0.7rem] text-red-700" x-show="overLimit(@js($key))" x-cloak>Vượt giới hạn — xử lý bớt trước khi nhận thêm</p>
                @if($key === 'delivered')
                    <p class="px-3 -mt-1 pb-1.5 text-[0.7rem] text-[var(--muted)]" x-show="over === 'delivered' && blocked" x-cloak>Đơn này chưa có link file bàn giao</p>
                @endif
                <div data-cards class="board-cards flex-1 overflow-y-auto px-2 pb-2 min-h-20">
                    @foreach($list as $order)
                        @include('orders._card', ['order' => $order])
                    @endforeach
                </div>
                <div class="board-col-foot px-2 pb-2" x-show="remaining[@js($key)] > 0" x-cloak>
                    <button type="button" class="w-full rounded-md py-1.5 text-xs font-semibold text-[var(--ink-2)] hover:bg-white/80 disabled:opacity-60" @click="loadMore(@js($key))" :disabled="loading[@js($key)]">
                        <span x-show="!loading[@js($key)]" x-text="'Tải thêm · còn ' + remaining[@js($key)] + ' đơn'"></span>
                        <span x-show="loading[@js($key)]"><i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>Đang tải…</span>
                    </button>
                </div>
                <p data-empty class="board-empty px-3 pb-4 text-xs text-[var(--muted)] text-center" @if($list->isNotEmpty() || ($totals[$key]['count'] ?? 0) > 0) hidden @endif>{{ $board->isFiltered() ? 'Không có đơn khớp' : 'Kéo thẻ vào đây' }}</p>
            </section>
        @endforeach
    </div>

    {{-- Thanh thao tác khi chọn nhiều thẻ --}}
    <div class="fixed bottom-20 md:bottom-5 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-xl" x-show="selected.length" x-cloak x-transition>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg bg-[var(--ink)] text-white px-4 py-3 text-sm shadow-lg">
            <span class="font-semibold tnum" x-text="'Đã chọn ' + selected.length + ' đơn'"></span>
            <label for="bulk-status" class="sr-only">Chuyển các đơn đã chọn sang</label>
            <select id="bulk-status" class="rounded-md bg-white/10 border border-white/25 py-1.5 pl-2 pr-7 text-sm text-white" @change="bulkMove($el.value); $el.value = ''" :disabled="busy">
                <option value="" class="text-[var(--ink)]">Chuyển sang…</option>
                @foreach(config('net.statuses') as $k => $st)<option value="{{ $k }}" class="text-[var(--ink)]">{{ $st['label'] }}</option>@endforeach
            </select>
            <span x-show="busy" class="text-white/80"><i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>Đang chuyển…</span>
            <button type="button" class="ml-auto underline underline-offset-2 opacity-90 hover:opacity-100" @click="clearSelection()">Bỏ chọn <kbd class="kbd-hint kbd-dark">Esc</kbd></button>
        </div>
    </div>

    {{-- Thông báo sau khi chuyển (kèm Hoàn tác) / lỗi --}}
    <div class="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-2rem)] max-w-md" aria-live="polite">
        <template x-if="toast">
            <div class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm shadow-lg" :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-[var(--ink)] text-white'" x-transition>
                <i class="fa-solid" :class="toast.tone === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'" aria-hidden="true"></i>
                <span class="flex-1" x-text="toast.text"></span>
                <button type="button" x-show="!!toast.undo" class="font-semibold underline underline-offset-2 shrink-0" @click="runUndo()">Hoàn tác</button>
                <button type="button" class="shrink-0 opacity-70 hover:opacity-100" @click="toast = null" aria-label="Đóng thông báo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
        </template>
    </div>
    <p class="sr-only" aria-live="assertive" x-text="live"></p>
</div>
@endsection
