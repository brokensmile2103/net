@extends('layouts.guest')
@section('title', 'Đặt lại mật khẩu · Nét')
@section('meta_description', 'Đặt lại mật khẩu tài khoản Nét.')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Tạo mật khẩu mới cho tài khoản của bạn')
@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" label="Email" icon="fa-envelope" value="{{ old('email', $email) }}" required autofocus autocomplete="email" />
        <x-input name="password" type="password" label="Mật khẩu mới" icon="fa-lock" required minlength="8" autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Nhập lại mật khẩu mới" icon="fa-lock" required minlength="8" autocomplete="new-password" />

        <x-button variant="ink" icon="fa-key" class="w-full">Đặt lại mật khẩu</x-button>
    </form>

    <p class="text-center text-sm text-[var(--muted)] mt-5">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-[var(--accent-ink)] font-semibold hover:underline">
            <i class="fa-solid fa-arrow-left"></i>Quay lại đăng nhập
        </a>
    </p>
@endsection
