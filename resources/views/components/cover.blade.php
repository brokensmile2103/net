{{-- Ảnh bìa đơn trong khung có dấu góc cắt; chưa có ảnh thì hiện nền ô lưới + mã đơn. --}}
@props(['order', 'ratio' => '16 / 10', 'crop' => true])
<div {{ $attributes->merge(['class' => ($crop ? 'crop ' : '').'block']) }}>
    <div class="relative overflow-hidden rounded-md bg-white ring-1 ring-[var(--line)]" style="aspect-ratio: {{ $ratio }}">
        @if($order->coverUrl())
            <img src="{{ $order->coverUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
        @else
            <div class="absolute inset-0 canvas-empty flex items-center justify-center">
                <span class="font-display text-lg font-bold text-stone-300 tracking-tight">{{ $order->code }}</span>
            </div>
        @endif
    </div>
</div>
