{{-- 1 vòng phương án. Biến: $order, $round --}}
@php
    $locked = in_array($round->status, ['approved', 'changes'], true);
    $pins = $round->feedback->filter(fn ($f) => $f->hasPin())->values();
    $pinNo = $pins->mapWithKeys(fn ($f, $i) => [$f->id => $i + 1]);
    $general = $round->feedback->filter(fn ($f) => ! $f->hasPin());
    $tone = ['draft' => 'bg-stone-100 text-stone-600', 'sent' => 'bg-[var(--accent-soft)] text-[var(--accent-ink)]', 'approved' => 'bg-emerald-50 text-emerald-700', 'changes' => 'bg-orange-50 text-orange-700'][$round->status] ?? '';
@endphp
<article id="vong-{{ $round->id }}" class="scroll-mt-20 rounded-lg border {{ $round->isDraft() ? 'border-dashed border-[var(--line-strong)]' : 'border-[var(--line)]' }} bg-white mb-4">
    <header class="px-5 py-3.5 flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[var(--line)]">
        <span class="font-display text-2xl font-extrabold tracking-tight">{{ $round->label() }}</span>
        <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 {{ $tone }}">{{ $round->statusLabel() }}</span>
        <span class="text-xs text-[var(--muted)] ml-auto">
            @if($round->sent_at) Gửi {{ $round->sent_at->format('H:i d/m') }} @endif
            @if($round->responded_at) · khách phản hồi {{ $round->responded_at->format('H:i d/m') }} @endif
        </span>
    </header>

    <div class="p-5 space-y-5">
        @if($round->note && ! $round->isDraft())
            <p class="text-sm text-[var(--ink-2)] whitespace-pre-line"><span class="text-[var(--muted)]">Lời nhắn kèm:</span> {{ $round->note }}</p>
        @endif

        {{-- Phản hồi của khách --}}
        @if($round->responded_at || $round->feedback->isNotEmpty())
            <div class="rounded-md border-l-4 {{ $round->status === 'approved' ? 'border-emerald-500 bg-emerald-50/60' : 'border-orange-400 bg-orange-50/60' }} px-4 py-3">
                <p class="text-sm font-semibold">{{ $round->status === 'approved' ? 'Khách đã duyệt' : 'Khách góp ý' }}</p>
                @foreach($general as $fb)
                    <p class="text-sm whitespace-pre-line mt-1">{{ $fb->body }}</p>
                @endforeach
                @if($pins->isNotEmpty())
                    <ol class="mt-2 space-y-1.5">
                        @foreach($pins as $fb)
                            <li class="flex gap-2 text-sm {{ $fb->resolved_at ? 'text-[var(--muted)] line-through' : '' }}">
                                <span class="w-5 h-5 shrink-0 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.65rem] font-bold flex items-center justify-center">{{ $pinNo[$fb->id] }}</span>
                                <span class="flex-1">{{ $fb->body }} <span class="text-xs text-[var(--muted)] no-underline">— {{ $fb->proposal?->label }}</span></span>
                                <form method="POST" action="{{ route('feedback.resolve', $fb->id) }}">@csrf
                                    <button class="text-xs text-[var(--muted)] hover:text-[var(--ink)] whitespace-nowrap no-underline" title="{{ $fb->resolved_at ? 'Bỏ đánh dấu' : 'Đánh dấu đã sửa' }}">
                                        <i class="fa-{{ $fb->resolved_at ? 'solid' : 'regular' }} fa-square-check" aria-hidden="true"></i><span class="sr-only">Đã sửa</span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif

        {{-- Các phương án --}}
        <div class="grid sm:grid-cols-2 gap-5">
            @foreach($round->proposals as $p)
                <div class="min-w-0" x-data="{ edit: false, cur: 0 }">
                    <div class="space-y-2">
                        @forelse($p->images as $img)
                            <div class="relative" x-show="cur === {{ $loop->index }}" @if(! $loop->first) x-cloak @endif>
                                <a href="{{ $img->url() }}" target="_blank" rel="noopener" class="block crop">
                                    <img src="{{ $img->url() }}" alt="{{ $p->label }} — ảnh {{ $loop->iteration }}" style="aspect-ratio: {{ $img->aspect() }}" class="w-full rounded-md ring-1 ring-[var(--line)] object-cover bg-stone-50" loading="lazy">
                                </a>
                                @foreach($pins->where('image_id', $img->id) as $fb)
                                    <span class="absolute w-6 h-6 -ml-3 -mt-3 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.7rem] font-bold flex items-center justify-center ring-2 ring-white shadow" style="left: {{ $fb->pin_x }}%; top: {{ $fb->pin_y }}%" title="{{ $fb->body }}">{{ $pinNo[$fb->id] }}</span>
                                @endforeach
                            </div>
                        @empty
                            <div class="canvas-empty rounded-md ring-1 ring-[var(--line)] aspect-[4/3] flex items-center justify-center text-sm text-[var(--muted)]">Không có ảnh xem trước</div>
                        @endforelse
                        @if($p->images->count() > 1)
                            <div class="flex gap-1.5 overflow-x-auto scrollbar-hide">
                                @foreach($p->images as $img)
                                    <div class="relative shrink-0">
                                        <button type="button" @click="cur = {{ $loop->index }}" class="block rounded" :class="cur === {{ $loop->index }} ? 'ring-2 ring-[var(--ink)]' : ''" aria-label="Xem ảnh {{ $loop->iteration }}"><img src="{{ $img->url() }}" alt="" class="w-14 h-14 rounded object-cover ring-1 ring-[var(--line)]" loading="lazy"></button>
                                        @if($pins->where('image_id', $img->id)->isNotEmpty())<span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-[var(--accent)] text-[var(--on-accent)] text-[0.55rem] font-bold flex items-center justify-center">{{ $pins->where('image_id', $img->id)->count() }}</span>@endif
                                        @unless($locked)
                                            <form method="POST" action="{{ route('images.destroy', $img->id) }}" onsubmit="return confirm('Xoá ảnh này?')" class="absolute -bottom-1 -right-1">@csrf @method('DELETE')
                                                <button class="w-4 h-4 rounded-full bg-white ring-1 ring-[var(--line-strong)] text-[0.55rem] text-[var(--muted)] hover:text-red-600" aria-label="Xoá ảnh"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                            </form>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-2.5 flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $p->label }} @if($p->is_chosen)<span class="ml-1 text-xs font-semibold text-emerald-700 bg-emerald-50 rounded px-1.5 py-0.5"><i class="fa-solid fa-check mr-0.5" aria-hidden="true"></i>khách chọn</span>@endif</p>
                            @if($p->note)<p class="text-sm text-[var(--ink-2)] whitespace-pre-line mt-0.5">{{ $p->note }}</p>@endif
                            @if($p->drive_url)<a href="{{ $p->drive_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm text-[var(--accent-ink)] hover:underline mt-1"><i class="{{ link_icon($p->drive_url) }}" aria-hidden="true"></i>{{ link_host_label($p->drive_url) }}</a>@endif
                        </div>
                        @unless($locked)
                            <button type="button" class="text-[var(--muted)] hover:text-[var(--ink)] p-1 text-sm" @click="edit = !edit" aria-label="Sửa {{ $p->label }}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                            <form method="POST" action="{{ route('proposals.destroy', $p->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Xoá '.$p->label.' cùng toàn bộ ảnh?') }})">@csrf @method('DELETE')
                                <button class="text-[var(--muted)] hover:text-red-600 p-1 text-sm" aria-label="Xoá {{ $p->label }}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                            </form>
                        @endunless
                    </div>

                    @unless($locked)
                        <div x-show="edit" x-cloak class="mt-3 space-y-3 rounded-md bg-stone-50 border border-[var(--line)] p-3">
                            <form method="POST" action="{{ route('proposals.update', $p->id) }}" class="space-y-2">
                                @csrf @method('PUT')
                                <input name="label" value="{{ $p->label }}" maxlength="80" aria-label="Tên phương án" class="w-full rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm">
                                <textarea name="note" rows="2" maxlength="2000" aria-label="Ghi chú" placeholder="Ý tưởng, giải thích cho khách" class="w-full rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm">{{ $p->note }}</textarea>
                                <input type="url" name="drive_url" value="{{ $p->drive_url }}" aria-label="Link file" placeholder="https://drive.google.com/…" class="w-full rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm">
                                <x-button size="sm" variant="ink">Lưu</x-button>
                            </form>
                            @if($p->images->count() < config('net.images.per_proposal'))
                                <form method="POST" action="{{ route('proposals.images', $p->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2" data-paste-zone="Thêm ảnh vào {{ $p->label }}">
                                    @csrf
                                    <label class="text-sm">Thêm ảnh: <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required class="text-sm"></label>
                                    <x-button size="sm" variant="outline">Tải lên</x-button>
                                    <x-paste-hint class="basis-full" />
                                </form>
                            @endif
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>

        {{-- Thêm phương án (vòng nháp, hoặc vòng đã gửi nhưng khách chưa phản hồi) --}}
        @unless($locked)
            <details class="group rounded-md border border-[var(--line)] {{ $round->proposals->isEmpty() ? '' : '' }}" @if($round->proposals->isEmpty()) open @endif>
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold flex items-center gap-2"><i class="fa-solid fa-plus text-[var(--accent)]" aria-hidden="true"></i>Thêm phương án vào {{ $round->label() }}</summary>
                <form method="POST" action="{{ route('proposals.store', $round->id) }}" enctype="multipart/form-data" class="px-4 pb-4 space-y-3" x-data="filePreview({{ (int) config('net.images.per_proposal') }})" data-paste-zone="Ảnh phương án mới ({{ $round->label() }})">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-3">
                        <x-input name="label" id="label-{{ $round->id }}" label="Tên phương án" :placeholder="'Phương án '.chr(65 + min($round->proposals->count(), 25))" maxlength="80" />
                        <x-input type="url" name="drive_url" id="drive-{{ $round->id }}" label="Link file trên Drive (không bắt buộc)" placeholder="https://drive.google.com/…" />
                    </div>
                    <x-textarea name="note" id="note-{{ $round->id }}" label="Giải thích cho khách" rows="2" placeholder="VD: Hướng tối giản, lấy cảm hứng từ nét cọ thư pháp…" />
                    <div>
                        <label class="block text-sm font-medium text-[var(--ink-2)] mb-1.5" for="images-{{ $round->id }}">Ảnh xem trước (tối đa {{ config('net.images.per_proposal') }} ảnh)</label>
                        <input id="images-{{ $round->id }}" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @change="pick" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-[var(--ink)] file:text-white file:px-3 file:py-1.5 file:text-sm file:font-semibold">
                        <p class="text-xs text-[var(--muted)] mt-1">Ảnh tự thu nhỏ & nén nhẹ để khách xem nhanh. File gốc chất lượng cao để ở link Drive. <x-paste-hint prefix="Có thể" /> (dán nhiều lần để thêm nhiều ảnh).</p>
                        <p class="text-xs text-red-600 mt-1" x-show="tooMany" x-cloak>Chỉ lấy {{ config('net.images.per_proposal') }} ảnh đầu.</p>
                        <div class="flex gap-2 mt-2 flex-wrap" x-show="previews.length" x-cloak>
                            <template x-for="p in previews" :key="p.url"><img :src="p.url" :alt="p.name" class="w-16 h-16 rounded object-cover ring-1 ring-[var(--line)]"></template>
                        </div>
                    </div>
                    <x-button size="sm" variant="ink" icon="fa-plus">Thêm phương án</x-button>
                </form>
            </details>
        @endunless

        {{-- Gửi vòng nháp --}}
        @if($round->isDraft())
            <div class="flex flex-col sm:flex-row gap-3 sm:items-end border-t border-[var(--line)] pt-4">
                <form method="POST" action="{{ route('rounds.send', $round->id) }}" class="flex-1 space-y-2" onsubmit="return confirm('Gửi vòng {{ $round->label() }} cho khách? Khách sẽ thấy ngay trên trang theo dõi.')">
                    @csrf
                    <x-textarea name="note" id="send-note-{{ $round->id }}" label="Lời nhắn kèm (không bắt buộc)" rows="2" placeholder="VD: Mình gửi 3 hướng, bạn xem và ghim góp ý lên hình giúp mình nhé!">{{ $round->note }}</x-textarea>
                    <x-button icon="fa-paper-plane" :disabled="$round->proposals->isEmpty()">Gửi {{ $round->label() }} cho khách</x-button>
                </form>
                <form method="POST" action="{{ route('rounds.destroy', $round->id) }}" onsubmit="return confirm('Xoá vòng nháp {{ $round->label() }} cùng các phương án trong đó?')">@csrf @method('DELETE')
                    <x-button variant="ghost" size="sm" icon="fa-trash-can">Xoá vòng nháp</x-button>
                </form>
            </div>
        @elseif($round->awaitsClient())
            <p class="text-sm text-[var(--muted)] border-t border-[var(--line)] pt-4"><i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>Đang chờ khách xem. Bạn vẫn có thể thêm phương án — khách thấy ngay.</p>
        @endif
    </div>
</article>
