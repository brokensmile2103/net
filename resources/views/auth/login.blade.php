@extends('layouts.guest')
@section('title', 'Đăng nhập · Nét')
@section('robots', 'noindex, follow')
@section('subtitle', 'Đăng nhập để xem đơn hôm nay')
@section('content')
    @error('google')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm" role="alert"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ $message }}</div>
    @enderror
    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf
        <x-input name="phone" label="Số điện thoại" icon="fa-phone" value="{{ old('phone') }}" required autofocus inputmode="tel" autocomplete="tel" />
        <div>
            <x-input name="password" type="password" label="Mật khẩu" icon="fa-lock" required autocomplete="current-password" />
            <a href="{{ route('password.request') }}" class="inline-block mt-1.5 text-xs text-[var(--accent-ink)] hover:underline">Quên mật khẩu?</a>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded border-stone-300 accent-[var(--accent)]">
            <span class="text-sm text-[var(--ink-2)]">Ghi nhớ đăng nhập</span>
        </label>
        <x-form-guard name="login" />
        <x-button variant="ink" icon="fa-right-to-bracket" class="w-full">Đăng nhập</x-button>
    </form>
    <x-google-button />
    @if(\App\Models\PlatformSetting::registrationOpen())
        <p class="text-center text-sm text-[var(--muted)] mt-5">Chưa có tài khoản? <a href="{{ route('register') }}" class="text-[var(--accent-ink)] font-semibold hover:underline">Tạo studio miễn phí</a></p>
    @endif
@endsection
