{{-- Huy hiệu giai đoạn của đơn. --}}
@props(['order', 'client' => false])
@php $m = $order->meta(); @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap '.tone_badge($m['tone'])]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ tone_dot($m['tone']) }}" aria-hidden="true"></span>{{ $client ? $m['client_label'] : $m['label'] }}
</span>
