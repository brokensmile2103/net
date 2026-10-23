{{-- Vòng đã phản hồi (chỉ xem) trên trang theo dõi. Biến: $round --}}
@php
    $pins = $round->feedback->filter(fn ($f) => $f->hasPin())->values();
    $pinNo = $pins->mapWithKeys(fn ($f, $i) => [$f->id => $i + 1]);
    $general = $round->feedback->filter(fn ($f) => ! $f->hasPin());
@endphp
<details class="pf-card rounded-lg border border-[var(--line)] bg-white group" @if($loop->first && $round->status === 'approved') open @endif>
    <summary class="cursor-pointer list-none px-5 py-3.5 flex items-center gap-3">
        <span class="font-display text-xl font-extrabold tracking-tight">{{ $round->label() }}</span>
        <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 {{ $round->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }}">{{ $round->status === 'approved' ? 'Bạn đã duyệt' : 'Bạn đã góp ý' }}</span>
        <span class="text-xs text-[var(--muted)] ml-auto">{{ $round->sent_at?->format('d/m') }}</span>
        <i class="fa-solid fa-chevron-down text-xs text-[var(--muted)] transition group-open:rotate-180" aria-hidden="true"></i>
    </summary>
    <div class="px-5 pb-5 space-y-4">
        @if($round->note)<p class="text-sm text-[var(--ink-2)] whitespace-pre-line">{{ $round->note }}</p>@endif
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach($round->proposals as $p)
                <div class="min-w-0">
                    @foreach($p->images as $img)
                        <div class="relative mb-2">
                            <img src="{{ $img->url() }}" alt="{{ $p->label }} — ảnh {{ $loop->iteration }}" style="aspect-ratio: {{ $img->aspect() }}" class="w-full rounded-md ring-1 ring-[var(--line)] object-cover bg-stone-50 {{ $round->status === 'approved' && ! $p->is_chosen && $round->proposals->count() > 1 ? 'opacity-60' : '' }}" loading="lazy">
                            @foreach($pins->where('image_id', $img->id) as $fb)
                                <span class="absolute w-6 h-6 -ml-3 -mt-3 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.7rem] font-bold flex items-center justify-center ring-2 ring-white shadow" style="left: {{ $fb->pin_x }}%; top: {{ $fb->pin_y }}%" aria-hidden="true">{{ $pinNo[$fb->id] }}</span>
                            @endforeach
                        </div>
                    @endforeach
                    <p class="font-semibold text-sm">{{ $p->label }} @if($p->is_chosen)<span class="ml-1 text-xs text-emerald-700"><i class="fa-solid fa-check" aria-hidden="true"></i> đã chọn</span>@endif</p>
                </div>
            @endforeach
        </div>
        @if($general->isNotEmpty() || $pins->isNotEmpty())
            <div class="rounded-md bg-stone-50 px-4 py-3 text-sm space-y-1.5">
                <p class="font-semibold">Góp ý của bạn</p>
                @foreach($general as $fb)<p class="whitespace-pre-line">{{ $fb->body }}</p>@endforeach
                @foreach($pins as $fb)
                    <p class="flex gap-2"><span class="w-5 h-5 shrink-0 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.65rem] font-bold flex items-center justify-center">{{ $pinNo[$fb->id] }}</span><span>{{ $fb->body }}</span></p>
                @endforeach
            </div>
        @endif
    </div>
</details>
