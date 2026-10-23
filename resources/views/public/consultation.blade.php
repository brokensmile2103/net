@extends('layouts.public')
@section('title', 'Lịch hẹn tư vấn · '.$studio->name)
@section('share_title', 'Lịch hẹn gọi tư vấn với '.$studio->name)
@section('meta_description', $c->whenLabel().' · '.$c->channelLabel())
@section('frame', 'lich-hen')
@section('content')
@php $note = $studio->consultPrefs()['note']; @endphp
@if(session('booked'))
    <div class="pf-card mb-6 rounded-lg border-2 border-[var(--accent)] bg-white p-5" role="status">
        <p class="font-display text-xl font-bold">Đã đặt lịch!</p>
        <p class="text-sm text-[var(--ink-2)] mt-1">{{ $studio->name }} sẽ gọi bạn đúng giờ hẹn. Lưu link trang này để xem lại hoặc huỷ hẹn khi cần{{ $c->email && \App\Support\EmailCheck::plausible($c->email) ? ' — Nét cũng đã gửi xác nhận vào email của bạn' : '' }}.</p>
    </div>
@endif

<section class="pf-card rounded-lg border border-[var(--line)] bg-white p-5 md:p-6">
    <p class="text-sm text-[var(--muted)]">Lịch hẹn gọi tư vấn với {{ $studio->name }}</p>
    <h1 class="font-display text-2xl md:text-3xl font-bold tracking-tight mt-1 {{ $c->status === 'cancelled' ? 'line-through text-[var(--muted)]' : '' }}">{{ $c->whenLabel() }}</h1>
    <p class="mt-2 text-sm"><i class="fa-solid {{ config('net.consult.channels.'.$c->channel.'.icon', 'fa-phone') }} text-[var(--muted)] mr-1.5" aria-hidden="true"></i>{{ $c->channelLabel() }}
        @if($c->phone) · {{ $c->phone }} @endif
        <span class="ml-2 text-xs font-semibold rounded-full px-2 py-0.5 {{ $c->status === 'booked' ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600' }}">{{ $c->statusLabel() }}</span>
    </p>
    @if($c->note)<p class="mt-4 text-sm whitespace-pre-line"><span class="text-[var(--muted)]">Bạn muốn trao đổi:</span> {{ $c->note }}</p>@endif
    @if($note)<p class="mt-3 rounded-lg bg-[var(--accent-soft)] text-[var(--accent-ink)] px-4 py-3 text-sm whitespace-pre-line">{{ $note }}</p>@endif

    @if($c->isUpcoming())
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ $c->googleCalendarUrl() }}" target="_blank" rel="noopener" class="pf-btn pf-btn-ghost"><i class="fa-brands fa-google" aria-hidden="true"></i>Thêm vào Google Calendar</a>
            <a href="{{ route('public.consult.ics', $c->token) }}" class="pf-btn pf-btn-ghost"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>Tải file lịch (.ics)</a>
        </div>
        <form method="POST" action="{{ route('public.consult.cancel', $c->token) }}" class="mt-5" onsubmit="return confirm('Huỷ lịch hẹn này?')">@csrf
            <button class="text-sm text-[var(--muted)] hover:text-red-600 underline underline-offset-2">Huỷ lịch hẹn</button>
        </form>
    @endif
</section>

@if($studio->order_form_enabled)
    <p class="mt-6 text-sm text-center"><a href="{{ route('public.order', $studio->slug) }}" class="font-semibold text-[var(--accent-ink)] hover:underline">Xem bảng giá & đặt thiết kế với {{ $studio->name }} →</a></p>
@endif
@endsection
