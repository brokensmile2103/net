@extends('layouts.guest')
@section('title', 'Cài đặt Nét — Kiểm tra môi trường')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Bước 1/4 · Kiểm tra môi trường')
@section('content')
    <ul class="space-y-1 mb-6">
        @foreach ($checks as $label => $passed)
            <li class="flex items-center justify-between text-sm py-2 border-b border-[var(--line)] last:border-0">
                <span class="text-[var(--ink-2)]">{{ $label }}</span>
                @if($passed)
                    <span class="text-emerald-600"><i class="fa-solid fa-circle-check"></i></span>
                @elseif(str_contains($label, 'khuyến nghị'))
                    <span class="text-amber-600"><i class="fa-solid fa-circle-exclamation"></i></span>
                @else
                    <span class="text-red-600"><i class="fa-solid fa-circle-xmark"></i></span>
                @endif
            </li>
        @endforeach
    </ul>

    @if($allPassed)
        <a href="{{ route('install.database') }}">
            <x-button icon="fa-arrow-right">Tiếp tục</x-button>
        </a>
    @else
        <p class="text-sm text-red-600 mb-3 flex items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <span>Vui lòng liên hệ nhà cung cấp hosting để bật/cấp các mục còn thiếu ở trên, sau đó tải lại trang này.</span>
        </p>
        <a href="{{ route('install.requirements') }}">
            <x-button variant="ghost" icon="fa-rotate-right">Kiểm tra lại</x-button>
        </a>
    @endif
@endsection
