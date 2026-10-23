{{-- Brief + tài liệu tham khảo (link Drive) của đơn. Biến: $order --}}
@php
    $fields = array_filter([
        'Phong cách' => $order->style,
        'Màu sắc' => $order->colors,
        'Kích thước, định dạng' => $order->specs,
        'Ngân sách khách nêu' => $order->budget_note,
        'Khách mong nhận trước' => $order->client_due_on?->format('d/m/Y'),
        'Dịch vụ' => $order->service?->name,
    ]);
    $refs = $order->links->whereIn('kind', ['reference', 'other']);
@endphp
<section aria-labelledby="h-brief" class="rounded-lg border border-[var(--line)] bg-white">
    <div class="px-5 pt-4 pb-1 flex items-center justify-between gap-3">
        <h3 id="h-brief" class="font-display text-xl font-bold tracking-tight">Brief</h3>
        <a href="{{ route('orders.edit', $order->id) }}" class="text-sm text-[var(--accent-ink)] font-semibold hover:underline">Sửa</a>
    </div>
    <div class="px-5 pb-5">
        <p class="text-[0.95rem] leading-relaxed whitespace-pre-line">{{ $order->brief }}</p>

        @if($fields)
            <dl class="mt-4 grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                @foreach($fields as $label => $value)
                    <div><dt class="text-[var(--muted)] text-xs">{{ $label }}</dt><dd class="mt-0.5">{{ $value }}</dd></div>
                @endforeach
            </dl>
        @endif

        @if($answers = $order->briefAnswers())
            <div class="mt-4 rounded-md border border-[var(--line)] bg-stone-50/70 px-4 py-3">
                <p class="text-xs font-semibold text-[var(--muted)] mb-2"><i class="fa-regular fa-rectangle-list mr-1" aria-hidden="true"></i>Trả lời mẫu brief{{ $order->service ? ' · '.$order->service->name : '' }}</p>
                <dl class="space-y-2.5 text-sm">
                    @foreach($answers as $a)
                        <div><dt class="text-[var(--muted)] text-xs">{{ $a['question'] }}</dt><dd class="mt-0.5 whitespace-pre-line">{{ $a['answer'] }}</dd></div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if($order->copy_text)
            <div class="mt-4">
                <p class="text-xs text-[var(--muted)] mb-1">Nội dung chữ trên thiết kế</p>
                <div class="rounded-md bg-stone-50 border border-[var(--line)] px-3 py-2.5 text-sm whitespace-pre-line" x-data="{ copied: false }">
                    {{ $order->copy_text }}
                    <button type="button" class="block mt-2 text-xs font-semibold text-[var(--accent-ink)]" @click="copyText(@js($order->copy_text)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })" x-text="copied ? 'Đã chép' : 'Sao chép nội dung chữ'">Sao chép nội dung chữ</button>
                </div>
            </div>
        @endif
    </div>

    <div class="border-t border-[var(--line)] px-5 py-4" x-data="{ adding: false }">
        <div class="flex items-center justify-between gap-3 mb-2">
            <p class="text-sm font-semibold">Tài liệu & tham khảo</p>
            <button type="button" class="text-sm text-[var(--accent-ink)] font-semibold" @click="adding = !adding" x-text="adding ? 'Đóng' : '+ Thêm link'">+ Thêm link</button>
        </div>
        @forelse($refs as $link)
            <div class="flex items-center gap-3 py-1.5 text-sm group">
                <i class="{{ link_icon($link->url) }} w-4 text-center text-[var(--muted)]" aria-hidden="true"></i>
                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate hover:underline">{{ $link->displayLabel() }}</a>
                @if($link->from_client)<span class="text-[0.7rem] text-[var(--muted)] shrink-0">khách gửi</span>@endif
                <form method="POST" action="{{ route('links.destroy', $link->id) }}" class="ml-auto" onsubmit="return confirm('Gỡ link này khỏi đơn?')">@csrf @method('DELETE')
                    <button class="text-[var(--muted)] hover:text-red-600 p-1" aria-label="Gỡ link"><i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i></button>
                </form>
            </div>
        @empty
            <p class="text-sm text-[var(--muted)]">Chưa có link nào. Dán link thư mục Google Drive chứa logo cũ, ảnh sản phẩm, mẫu tham khảo của khách.</p>
        @endforelse

        <form x-show="adding" x-cloak method="POST" action="{{ route('orders.links.store', $order->id) }}" class="mt-3 grid sm:grid-cols-[1fr_160px_auto] gap-2">
            @csrf
            <input type="hidden" name="kind" value="reference">
            <label class="sr-only" for="ref-url">Link</label>
            <input id="ref-url" type="url" name="url" required placeholder="https://drive.google.com/…" class="rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm">
            <label class="sr-only" for="ref-label">Tên link</label>
            <input id="ref-label" name="label" maxlength="160" placeholder="Tên (không bắt buộc)" class="rounded-md border border-[var(--line-strong)] px-3 py-2 text-sm">
            <x-button size="sm" variant="ink">Thêm</x-button>
        </form>
    </div>
</section>
