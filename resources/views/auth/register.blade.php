@extends('layouts.guest')
@section('title', 'Tạo studio miễn phí · Nét')
@section('meta_description', 'Tạo studio trên Nét miễn phí — link đặt đơn riêng, theo dõi tiến độ, phương án & góp ý, đặt cọc và bàn giao cho freelancer thiết kế.')
@section('subtitle', 'Tạo studio của bạn — miễn phí, dùng được ngay')
@section('content')
    @if(\App\Http\Controllers\Auth\GoogleAuthController::enabled())
        <x-google-button label="Tạo studio bằng Google" :divider="false" />
        <div class="flex items-center gap-3 my-5" aria-hidden="true"><span class="flex-1 h-px bg-[var(--line)]"></span><span class="text-xs text-[var(--muted)] font-medium">hoặc đăng ký bằng số điện thoại</span><span class="flex-1 h-px bg-[var(--line)]"></span></div>
    @endif
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" x-data="{ name: @js(old('studio_name', '')), slug: @js(old('slug', '')), touched: {{ old('slug') ? 'true' : 'false' }} }">
        @csrf
        <x-input name="studio_name" label="Tên studio / tên làm nghề" icon="fa-pen-nib" placeholder="VD: Mây Studio, Huy Design" x-model="name"
                 x-on:input="if (!touched) slug = window.slugify(name)" required autofocus />
        <div>
            <label for="slug" class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">Địa chỉ trang đặt đơn</label>
            <div class="flex items-stretch rounded-lg border {{ $errors->has('slug') ? 'border-red-400' : 'border-[var(--line-strong)]' }} bg-white focus-within:ring-2 focus-within:ring-[var(--accent)]/25 focus-within:border-[var(--accent)] overflow-hidden">
                <span class="px-3 flex items-center text-sm text-[var(--muted)] bg-stone-50 border-r border-[var(--line)] whitespace-nowrap">{{ preg_replace('#^https?://#', '', url('/dat')) }}/</span>
                <input id="slug" name="slug" x-model="slug" x-on:input="touched = true" required maxlength="40" pattern="[a-z0-9-]{3,40}" autocomplete="off"
                       class="flex-1 min-w-0 py-2.5 px-3 text-[0.95rem] focus:outline-none" placeholder="may-studio">
            </div>
            @error('slug')<p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $message }}</p>@else<p class="mt-1 text-xs text-[var(--muted)]">Chữ thường không dấu, số và gạch ngang. Đổi được sau ở Cài đặt.</p>@enderror
        </div>
        <x-input name="name" label="Tên của bạn" icon="fa-user" value="{{ old('name') }}" required autocomplete="name" />
        <x-input name="phone" label="Số điện thoại (dùng để đăng nhập)" icon="fa-phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" />
        <x-input name="password" type="password" label="Mật khẩu (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Nhập lại mật khẩu" icon="fa-lock" required autocomplete="new-password" />
        <label class="flex items-start gap-2.5 cursor-pointer">
            <input type="checkbox" name="terms" value="1" required class="w-4 h-4 mt-0.5 rounded border-stone-300 accent-[var(--accent)] shrink-0">
            <span class="text-sm text-[var(--ink-2)] leading-relaxed">Tôi đồng ý với <a href="{{ route('pages.terms') }}" target="_blank" class="text-[var(--accent-ink)] font-medium hover:underline">Điều khoản sử dụng</a> và <a href="{{ route('pages.privacy') }}" target="_blank" class="text-[var(--accent-ink)] font-medium hover:underline">Chính sách bảo mật</a> của Nét.</span>
        </label>
        @error('terms')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <x-form-guard name="register" />
        <x-button variant="ink" icon="fa-pen-nib" class="w-full">Tạo studio</x-button>
    </form>
    <p class="text-center text-sm text-[var(--muted)] mt-5">Đã có tài khoản? <a href="{{ route('login') }}" class="text-[var(--accent-ink)] font-semibold hover:underline">Đăng nhập</a></p>
@endsection
