{{--
    "Tác phẩm tiêu biểu": lưới ảnh + xem lớn (bấm ảnh, ← → để chuyển, Esc để đóng). Mỗi mẫu giao diện tự trang trí
    qua class pf-works / pf-work. Biến: $works (Collection<PortfolioItem>), $studio.
--}}
@if($works->isNotEmpty())
    @php $gallery = $works->map(fn ($w) => ['src' => $w->url(), 'caption' => $w->caption])->values(); @endphp
    <section class="pf-works-wrap" aria-labelledby="h-works"
             x-data="{ items: @js($gallery), open: null,
                       show(i) { this.open = i; this.$nextTick(() => this.$refs.close?.focus()) },
                       step(d) { this.open = (this.open + d + this.items.length) % this.items.length } }"
             @keydown.escape.window="open = null" @keydown.arrow-right.window="open !== null && step(1)" @keydown.arrow-left.window="open !== null && step(-1)">
        <h2 id="h-works" class="pf-section-title">Tác phẩm tiêu biểu</h2>
        <div class="pf-works pf-works-{{ min($works->count(), 3) }}">
            @foreach($works as $i => $w)
                <figure class="pf-work">
                    <button type="button" class="pf-work-btn" @click="show({{ $i }})" aria-label="Xem lớn{{ $w->caption ? ': '.$w->caption : ' tác phẩm '.($i + 1) }}">
                        <img src="{{ $w->url() }}" alt="{{ $w->caption ?: 'Tác phẩm của '.$studio->name }}" style="aspect-ratio: {{ $w->aspect() }}" loading="{{ $i < 3 ? 'eager' : 'lazy' }}">
                    </button>
                    @if($w->caption)<figcaption>{{ $w->caption }}</figcaption>@endif
                </figure>
            @endforeach
        </div>

        <div x-show="open !== null" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4" @click.self="open = null" role="dialog" aria-modal="true" aria-label="Xem tác phẩm">
            <template x-if="open !== null">
                <figure class="max-w-5xl w-full flex flex-col items-center">
                    <img :src="items[open].src" :alt="items[open].caption || 'Tác phẩm'" class="max-h-[80vh] w-auto max-w-full object-contain">
                    <figcaption class="mt-3 text-sm text-white/85 text-center" x-show="items[open].caption" x-text="items[open].caption"></figcaption>
                </figure>
            </template>
            <button type="button" x-ref="close" class="absolute top-3 right-3 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white" @click="open = null" aria-label="Đóng"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            <template x-if="items.length > 1">
                <div>
                    <button type="button" class="absolute left-3 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white" @click="step(-1)" aria-label="Ảnh trước"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white" @click="step(1)" aria-label="Ảnh sau"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                </div>
            </template>
        </div>
    </section>
@endif
