{{-- 1 dòng đơn gọn (mục Hôm nay, trang khách). Biến: $order, $meta (chữ phụ, không bắt buộc) --}}
<a href="{{ route('orders.show', $order->id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
    <x-cover :order="$order" ratio="1 / 1" :crop="false" class="w-10 shrink-0" />
    <span class="min-w-0 flex-1">
        <span class="block text-sm font-semibold truncate">{{ $order->title }}</span>
        <span class="block text-xs text-[var(--muted)] truncate mt-0.5">{{ $order->code }} · {{ $order->client?->name }}@if($meta) · {{ $meta }}@endif</span>
    </span>
    @if($order->dueLabel() && $order->isOpen())
        <span class="text-xs whitespace-nowrap {{ $order->isOverdue() ? 'text-red-600 font-semibold' : 'text-[var(--muted)]' }}">{{ $order->dueLabel() }}</span>
    @else
        <x-status :order="$order" />
    @endif
</a>
