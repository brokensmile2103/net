{{-- 1 lịch hẹn tư vấn. Biến: $c --}}
<li class="rounded-lg border border-[var(--line)] bg-white px-4 py-3 flex flex-wrap items-start gap-x-4 gap-y-2 {{ $c->status === 'cancelled' ? 'opacity-60' : '' }}">
    <div class="w-28 shrink-0">
        <p class="font-semibold tnum whitespace-nowrap">{{ $c->starts_at->format('H:i') }}–{{ $c->endsAt()->format('H:i') }}</p>
        <p class="text-xs text-[var(--muted)] tnum">{{ $c->starts_at->format('d/m') }}</p>
    </div>
    <div class="min-w-0 flex-1">
        <p class="font-semibold">
            @if($c->client)<a href="{{ route('clients.show', $c->client_id) }}" class="hover:underline">{{ $c->name }}</a>@else{{ $c->name }}@endif
            <span class="ml-1.5 text-xs font-semibold rounded-full px-2 py-0.5 align-middle {{ ['booked' => 'bg-emerald-50 text-emerald-700', 'done' => 'bg-stone-100 text-stone-600', 'cancelled' => 'bg-red-50 text-red-700'][$c->status] ?? '' }}">{{ $c->statusLabel() }}</span>
        </p>
        <p class="text-sm text-[var(--ink-2)] mt-0.5">
            <i class="fa-solid {{ config('net.consult.channels.'.$c->channel.'.icon', 'fa-phone') }} text-[var(--muted)] mr-1" aria-hidden="true"></i>{{ $c->channelLabel() }}
            @if($c->phone) · <a href="tel:{{ $c->phone }}" class="hover:underline tnum">{{ $c->phone }}</a> · <a href="https://zalo.me/{{ preg_replace('/\D/', '', $c->phone) }}" target="_blank" rel="noopener" class="hover:underline">Zalo</a>@endif
            @if($c->email) · <a href="mailto:{{ $c->email }}" class="hover:underline">{{ $c->email }}</a>@endif
        </p>
        @if($c->note)<p class="text-sm text-[var(--muted)] mt-1 whitespace-pre-line">{{ $c->note }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        @if($c->status === 'booked')
            <form method="POST" action="{{ route('consultations.update', $c->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="done">
                <x-button size="sm" variant="outline" icon="fa-check">Đã gọi</x-button>
            </form>
            <form method="POST" action="{{ route('consultations.update', $c->id) }}" onsubmit="return confirm('Huỷ lịch hẹn với {{ $c->name }}?')">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled">
                <button class="text-[var(--muted)] hover:text-red-600 px-1">Huỷ</button>
            </form>
        @endif
        @if($c->client_id)
            <a href="{{ route('orders.create', ['khach' => $c->client_id]) }}" class="font-semibold text-[var(--accent-ink)] hover:underline">Tạo đơn</a>
        @endif
    </div>
</li>
