@extends('layouts.app')
@section('title', 'Nhật ký email · Nét')
@section('page-title', 'Nhật ký email gửi khách')
@section('actions')
    <x-button size="sm" variant="outline" icon="fa-gear" :href="route('settings.index').'#email-khach'">Cài đặt email</x-button>
@endsection
@section('content')
@php
    $filters = ['' => 'Tất cả', 'sent' => 'Đã gửi', 'sandbox' => 'Chế độ thử', 'pending' => 'Chờ gửi', 'failed' => 'Lỗi', 'skipped' => 'Bỏ qua', 'cancelled' => 'Đã huỷ'];
@endphp
<div class="px-4 py-5 md:px-8 md:py-7 max-w-5xl mx-auto">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex flex-wrap gap-1.5">
            @foreach($filters as $k => $label)
                <a href="{{ route('settings.email.log', array_filter(['trang-thai' => $k])) }}" class="px-3 py-1 rounded-full text-sm border {{ $status === $k ? 'bg-[var(--ink)] text-white border-[var(--ink)]' : 'bg-white border-[var(--line)] text-[var(--ink-2)] hover:border-[var(--line-strong)]' }}">{{ $label }}</a>
            @endforeach
        </div>
        <p class="ml-auto text-sm text-[var(--muted)] tnum">Hôm nay: {{ $usedToday }} / {{ $limit }} email</p>
    </div>

    <div class="rounded-lg border border-[var(--line)] bg-white overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-[var(--muted)] border-b border-[var(--line)]">
                <th class="px-4 py-2 font-medium">Lúc</th><th class="px-4 py-2 font-medium">Loại</th><th class="px-4 py-2 font-medium">Đơn</th><th class="px-4 py-2 font-medium">Tới</th><th class="px-4 py-2 font-medium">Trạng thái</th>
            </tr></thead>
            <tbody class="divide-y divide-[var(--line)]">
                @forelse($logs as $log)
                    @php [$label, $cls] = $log->statusLabel(); @endphp
                    <tr class="align-top">
                        <td class="px-4 py-2 tnum whitespace-nowrap text-[var(--muted)]">{{ ($log->sent_at ?? $log->send_at ?? $log->created_at)->format('d/m H:i') }}</td>
                        <td class="px-4 py-2">{{ $log->kindLabel() }}@if($log->subject)<span class="block text-xs text-[var(--muted)] truncate max-w-xs">{{ $log->subject }}</span>@endif</td>
                        <td class="px-4 py-2">@if($log->order)<a href="{{ route('orders.show', $log->order->id) }}" class="font-semibold hover:underline">{{ $log->order->code }}</a>@else — @endif</td>
                        <td class="px-4 py-2 break-all">{{ $log->to_email }}</td>
                        <td class="px-4 py-2">
                            <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $cls }}">{{ $label }}</span>
                            @if($log->note)<span class="block text-xs text-[var(--muted)] mt-1">{{ $log->note }}</span>@endif
                            @if($log->status === 'pending')
                                <span class="flex gap-3 mt-1">
                                    <form method="POST" action="{{ route('emails.pending', $log->id) }}">@csrf<input type="hidden" name="action" value="send"><button class="text-xs font-semibold text-[var(--accent-ink)] hover:underline">Gửi ngay</button></form>
                                    <form method="POST" action="{{ route('emails.pending', $log->id) }}">@csrf<input type="hidden" name="action" value="cancel"><button class="text-xs text-[var(--muted)] hover:text-red-600">Huỷ</button></form>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-[var(--muted)]">Chưa có email nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
    <p class="text-xs text-[var(--muted)] mt-4">Nhật ký giữ {{ config('net.mail.keep_days') }} ngày. Chỉ bạn xem được nhật ký của studio mình.</p>
</div>
@endsection
