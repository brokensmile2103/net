{{--
    Bảng lệnh (Ctrl/⌘ + K) và hộp "Phím tắt" (?) của khu làm việc. JS: resources/js/shortcuts.js.
    Cách gõ không dấu vẫn tìm ra lệnh ("bang viec" → Bảng việc).
--}}
@php
    $plain = fn ($t) => \Illuminate\Support\Str::of($t)->ascii()->lower()->toString();
    $commands = collect([
        ['label' => 'Tạo đơn mới', 'icon' => 'fa-plus', 'url' => route('orders.create'), 'keys' => 'Alt N'],
        ['label' => 'Hôm nay', 'icon' => 'fa-sun', 'url' => route('today'), 'keys' => 'G H'],
        ['label' => 'Bảng việc', 'icon' => 'fa-table-columns', 'url' => route('orders.board'), 'keys' => 'G B'],
        ['label' => 'Tất cả đơn', 'icon' => 'fa-list', 'url' => route('orders.index'), 'keys' => 'G D'],
        ['label' => 'Đơn trễ hạn', 'icon' => 'fa-triangle-exclamation', 'url' => route('orders.index', ['loc' => 'overdue']), 'keys' => ''],
        ['label' => 'Đơn còn nợ', 'icon' => 'fa-coins', 'url' => route('orders.index', ['loc' => 'unpaid']), 'keys' => ''],
        ['label' => 'Lịch hạn chót', 'icon' => 'fa-calendar', 'url' => route('orders.calendar'), 'keys' => 'G L'],
        ['label' => 'Khách hàng', 'icon' => 'fa-address-book', 'url' => route('clients.index'), 'keys' => 'G K'],
        ['label' => 'Dịch vụ & bảng giá', 'icon' => 'fa-tags', 'url' => route('services.index'), 'keys' => 'G V'],
        ['label' => 'Báo cáo', 'icon' => 'fa-chart-simple', 'url' => route('reports.index'), 'keys' => 'G R'],
        ['label' => 'Cài đặt studio', 'icon' => 'fa-gear', 'url' => route('settings.index'), 'keys' => 'G C'],
        ['label' => 'Hẹn gọi tư vấn', 'icon' => 'fa-calendar-check', 'url' => route('consultations.index'), 'keys' => ''],
        ['label' => 'Nhắc khách tự động (email)', 'icon' => 'fa-envelope', 'url' => route('settings.index').'#nhac-khach', 'keys' => ''],
        ['label' => 'Giao diện trang đặt đơn', 'icon' => 'fa-palette', 'url' => route('settings.index').'#trang-dat-don', 'keys' => ''],
        ['label' => 'Hồ sơ cá nhân', 'icon' => 'fa-circle-user', 'url' => route('profile.edit'), 'keys' => ''],
    ])->when($studio, fn ($c) => $c->push(['label' => 'Mở trang đặt đơn của bạn', 'icon' => 'fa-arrow-up-right-from-square', 'url' => $studio->orderFormUrl(), 'keys' => '']))
      ->map(fn ($c) => $c + ['search' => $plain($c['label'])])->values();
    $groups = [
        'Chung' => [['Alt', 'N', 'Tạo đơn mới'], ['Ctrl', 'K', 'Bảng lệnh: tìm đơn, khách, mở trang'], ['/', null, 'Tìm trên trang đang xem'], ['?', null, 'Mở hộp phím tắt này'], ['E', null, 'Sửa đơn (trong trang đơn)']],
        'Chuyển trang (bấm G rồi…)' => [['G', 'H', 'Hôm nay'], ['G', 'B', 'Bảng việc'], ['G', 'D', 'Tất cả đơn'], ['G', 'L', 'Lịch hạn chót'], ['G', 'K', 'Khách hàng'], ['G', 'V', 'Dịch vụ'], ['G', 'R', 'Báo cáo'], ['G', 'C', 'Cài đặt']],
        'Bảng việc' => [['←↑↓→', null, 'Đi giữa các thẻ'], ['Enter', null, 'Mở đơn'], ['Shift', '← →', 'Chuyển thẻ sang cột bên cạnh'], ['X', null, 'Chọn / bỏ chọn thẻ (chọn nhiều để chuyển một lượt)'], ['1 2 3', null, 'Xem: Thẻ · Gọn · Dòng'], ['Esc', null, 'Bỏ chọn / huỷ kéo']],
    ];
@endphp

{{-- Bảng lệnh --}}
<div x-data="cmdk(@js(route('quick-search')), @js($commands))" @net-cmdk.window="open ? close() : show()" :data-modal-open="open ? '' : null" class="cmdk">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 flex items-start justify-center p-4 pt-[12vh]" @click.self="close()">
        <div class="w-full max-w-xl rounded-xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden" role="dialog" aria-modal="true" aria-label="Bảng lệnh">
            <div class="flex items-center gap-3 px-4 border-b border-[var(--line)]">
                <i class="fa-solid fa-magnifying-glass text-[var(--muted)]" aria-hidden="true"></i>
                <input x-ref="input" id="cmdk-input" name="cmdk" x-model="q" @input="input()" @keydown="keydown($event)" type="text" autocomplete="off" spellcheck="false"
                       placeholder="Tìm đơn (mã, tên dự án, khách), khách hàng, hoặc gõ tên trang…" aria-label="Tìm hoặc gõ lệnh"
                       role="combobox" aria-controls="cmdk-list" :aria-expanded="items.length > 0" :aria-activedescendant="'cmdk-' + active"
                       class="cmdk-input flex-1 py-3.5 text-[0.95rem] bg-transparent">
                <i class="fa-solid fa-spinner fa-spin text-[var(--muted)] text-sm" x-show="loading" x-cloak aria-hidden="true"></i>
                <kbd class="kbd-hint">Esc</kbd>
            </div>
            <ul id="cmdk-list" x-ref="list" role="listbox" class="max-h-[55vh] overflow-y-auto py-1.5">
                <template x-for="(item, i) in items" :key="item.group + item.url + i">
                    <li>
                        <p x-show="i === 0 || items[i - 1].group !== item.group" class="px-4 pt-2.5 pb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--muted)]" x-text="item.group"></p>
                        <a :href="item.url" :id="'cmdk-' + i" role="option" :aria-selected="(i === active).toString()" @mouseenter="active = i" @click.prevent="run(item, $event)"
                           class="mx-1.5 px-2.5 py-2 rounded-md flex items-center gap-3 text-sm" :class="i === active ? 'bg-[var(--accent-soft)] text-[var(--ink)]' : 'text-[var(--ink-2)]'">
                            <template x-if="item.cover"><img :src="item.cover" alt="" class="w-8 h-8 rounded object-cover shrink-0"></template>
                            <template x-if="!item.cover"><span class="w-8 h-8 rounded bg-stone-100 flex items-center justify-center shrink-0 text-[var(--muted)]"><i class="fa-solid" :class="item.icon" aria-hidden="true"></i></span></template>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium" x-text="item.label"></span>
                                <span class="block truncate text-xs text-[var(--muted)]" x-show="item.detail" x-text="item.detail"></span>
                            </span>
                            <span class="text-[0.7rem] text-[var(--muted)] shrink-0" x-show="item.keys" x-text="isMac ? (item.keys || '').replace('Alt', '⌥') : item.keys"></span>
                        </a>
                    </li>
                </template>
                <li x-show="q.trim() && !loading && items.length === 0" class="px-4 py-6 text-sm text-center text-[var(--muted)]">Không tìm thấy gì khớp “<span x-text="q"></span>”.</li>
            </ul>
            <p class="px-4 py-2 border-t border-[var(--line)] text-[0.7rem] text-[var(--muted)] flex flex-wrap gap-x-4 gap-y-1">
                <span><kbd class="kbd-hint">↑</kbd><kbd class="kbd-hint">↓</kbd> chọn</span><span><kbd class="kbd-hint">Enter</kbd> mở</span>
                <span><kbd class="kbd-hint" x-text="isMac ? '⌘' : 'Ctrl'">Ctrl</kbd> + <kbd class="kbd-hint">Enter</kbd> mở tab mới</span>
            </p>
        </div>
    </div>
</div>

{{-- Hộp phím tắt --}}
<div x-data="{ open: false, isMac: /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '') }" @net-shortcuts.window="open = !open" @keydown.escape.window="open = false" :data-modal-open="open ? '' : null">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 flex items-center justify-center p-4" @click.self="open = false">
        <div class="w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-xl bg-white shadow-2xl p-5 md:p-6" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title">
            <div class="flex items-center gap-3 mb-4">
                <h2 id="shortcuts-title" class="font-display text-lg font-bold tracking-tight flex-1"><i class="fa-regular fa-keyboard mr-2 text-[var(--accent)]" aria-hidden="true"></i>Phím tắt</h2>
                <button type="button" class="w-8 h-8 rounded-md hover:bg-stone-100" @click="open = false" aria-label="Đóng"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="grid md:grid-cols-2 gap-x-8 gap-y-5">
                @foreach($groups as $title => $rows)
                    <section class="{{ $loop->last ? 'md:col-span-2' : '' }}">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--muted)] mb-2">{{ $title }}</h3>
                        <ul class="space-y-1.5 {{ $loop->last ? 'md:grid md:grid-cols-2 md:gap-x-8 md:space-y-0 md:gap-y-1.5' : '' }}">
                            @foreach($rows as [$a, $b, $label])
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="w-24 shrink-0 flex items-center gap-1">
                                        @if($a === 'Alt')<kbd class="kbd" x-text="isMac ? '⌥' : 'Alt'">Alt</kbd>@elseif($a === 'Ctrl')<kbd class="kbd" x-text="isMac ? '⌘' : 'Ctrl'">Ctrl</kbd>@else<kbd class="kbd">{{ $a }}</kbd>@endif
                                        @if($b)<span class="text-[var(--muted)] text-xs">{{ $a === 'G' ? 'rồi' : '+' }}</span><kbd class="kbd">{{ $b }}</kbd>@endif
                                    </span>
                                    <span class="text-[var(--ink-2)]">{{ $label }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>
