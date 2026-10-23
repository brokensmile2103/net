@extends('layouts.guest')
@section('title', 'Hoàn tất tạo studio · Nét')
@section('robots', 'noindex, follow')
@section('subtitle', 'Còn một bước nữa thôi')
@section('content')
    <div class="flex items-center gap-3 mb-5 rounded-lg bg-stone-50 border border-[var(--line)] p-3">
        @if(! empty($pending['avatar']))
            <img src="{{ $pending['avatar'] }}" alt="" class="w-10 h-10 rounded-full shrink-0" referrerpolicy="no-referrer">
        @else
            <span class="w-10 h-10 rounded-full bg-[var(--ink)] text-white flex items-center justify-center shrink-0" aria-hidden="true"><i class="fa-brands fa-google"></i></span>
        @endif
        <div class="min-w-0">
            <p class="text-sm font-semibold truncate">{{ $pending['name'] ? 'Chào '.$pending['name'].'!' : 'Đã xác thực với Google' }}</p>
            <p class="text-xs text-[var(--muted)] truncate">{{ $pending['email'] ?? 'Tài khoản Google' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('register.google.store') }}" class="space-y-4"
          x-data="{ name: @js(old('studio_name', '')), slug: @js(old('slug', '')), touched: {{ old('slug') ? 'true' : 'false' }} }">
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
            @error('slug')<p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $message }}</p>@enderror
        </div>
        <x-input name="name" label="Tên của bạn" icon="fa-user" :value="old('name', $pending['name'] ?? '')" required autocomplete="name" />
        <x-input name="phone" label="Số điện thoại" icon="fa-phone" :value="old('phone')" required inputmode="tel" autocomplete="tel"
                 hint="Để khách liên hệ và để đăng nhập dự phòng khi không dùng được Google." />
        <label class="flex items-start gap-2.5 cursor-pointer">
            <input type="checkbox" name="terms" value="1" required class="w-4 h-4 mt-0.5 rounded border-stone-300 accent-[var(--accent)] shrink-0">
            <span class="text-sm text-[var(--ink-2)] leading-relaxed">Tôi đồng ý với <a href="{{ route('pages.terms') }}" target="_blank" class="text-[var(--accent-ink)] font-medium hover:underline">Điều khoản sử dụng</a> và <a href="{{ route('pages.privacy') }}" target="_blank" class="text-[var(--accent-ink)] font-medium hover:underline">Chính sách bảo mật</a> của Nét.</span>
        </label>
        @error('terms')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <x-form-guard name="register-google" />
        <x-button variant="ink" icon="fa-pen-nib" class="w-full">Tạo studio</x-button>
    </form>
    <p class="text-center text-sm text-[var(--muted)] mt-5"><a href="{{ route('login') }}" class="hover:underline">Huỷ, quay lại đăng nhập</a></p>
@endsection
