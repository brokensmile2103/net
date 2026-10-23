@extends('layouts.app')
@section('title', 'Hẹn tư vấn · Nét')
@section('page-title', 'Hẹn gọi tư vấn')
@section('actions')
    <x-button size="sm" variant="outline" icon="fa-gear" :href="route('settings.index').'#tu-van'">Khung giờ nhận hẹn</x-button>
@endsection
@section('content')
@php
    $row = function ($c) {
        return view('consultations._row', ['c' => $c])->render();
    };
    $groups = $upcoming->groupBy(fn ($c) => $c->starts_at->toDateString());
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-4xl mx-auto space-y-8">
    @unless($studio->consultEnabled())
        <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
            Hẹn gọi tư vấn đang tắt trên trang đặt đơn. <a href="{{ route('settings.index') }}#tu-van" class="font-semibold underline">Bật trong Cài đặt</a>
        </div>
    @endunless

    <section aria-labelledby="h-up">
        <h2 id="h-up" class="font-display text-lg font-bold tracking-tight mb-3">Sắp tới <span class="text-sm font-normal text-[var(--muted)] tnum">{{ $upcoming->count() }}</span></h2>
        @forelse($groups as $date => $list)
            @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
            <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--muted)] mt-4 mb-2">{{ $d->isToday() ? 'Hôm nay' : ($d->isTomorrow() ? 'Ngày mai' : config('net.consult.weekdays.'.$d->isoWeekday())) }} · {{ $d->format('d/m') }}</h3>
            <ul class="space-y-2">@foreach($list as $c){!! $row($c) !!}@endforeach</ul>
        @empty
            <div class="rounded-lg border border-dashed border-[var(--line-strong)] bg-white px-6 py-10 text-center text-sm text-[var(--muted)]">Chưa có lịch hẹn nào sắp tới.</div>
        @endforelse
    </section>

    @if($past->isNotEmpty())
        <section aria-labelledby="h-past">
            <h2 id="h-past" class="font-display text-lg font-bold tracking-tight mb-3">Đã qua (60 ngày)</h2>
            <ul class="space-y-2">@foreach($past as $c){!! $row($c) !!}@endforeach</ul>
        </section>
    @endif
</div>
@endsection
