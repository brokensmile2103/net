@extends('layouts.guest')
@section('title', 'Quên mật khẩu · Nét')
@section('meta_description', 'Lấy lại mật khẩu Nét qua email khôi phục đã đăng ký ở Hồ sơ cá nhân.')
@section('robots', 'noindex, follow')
@section('subtitle', 'Lấy lại mật khẩu qua email đã đăng ký')
@section('content')
    @if (session('status'))
        <div class="mb-5 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            <i class="fa-solid fa-circle-check mr-1"></i>{{ session('status') }}
        </div>
    @else
        <p class="text-sm text-[var(--muted)] mb-5">
            Nhập số điện thoại đăng nhập của bạn. Nếu tài khoản đã có <strong>email khôi phục</strong>
            (thiết lập trong Hồ sơ cá nhân), chúng tôi sẽ gửi link đặt lại mật khẩu tới email đó.
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-input name="phone" label="Số điện thoại" icon="fa-phone" value="{{ old('phone') }}" required autofocus inputmode="tel" autocomplete="tel" />

            <x-form-guard name="forgot-password" />

            <x-button variant="ink" icon="fa-paper-plane" class="w-full">Gửi link đặt lại mật khẩu</x-button>
        </form>
    @endif

    <p class="text-center text-sm text-[var(--muted)] mt-5">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-[var(--accent-ink)] font-semibold hover:underline">
            <i class="fa-solid fa-arrow-left"></i>Quay lại đăng nhập
        </a>
    </p>
@endsection
