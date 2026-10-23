@extends('layouts.guest')
@section('title', 'Cài đặt Nét — Tạo tài khoản Super Admin')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Bước 3/3 · Tạo tài khoản Super Admin')
@section('content')
    <p class="text-xs text-[var(--muted)] mb-4 -mt-2">Tài khoản này vừa là Super Admin (quản lý toàn bộ hệ thống) vừa là chủ của studio đầu tiên, nên dùng được ngay mọi tính năng. Freelancer khác có thể tự đăng ký sau khi cài xong — hoặc bạn đóng đăng ký trong Cài đặt hệ thống nếu chỉ tự dùng.</p>
    <form method="POST" action="{{ route('install.account.save') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Tên của bạn" icon="fa-user" value="{{ old('name') }}" required autofocus autocomplete="name" />
        <x-input name="studio_name" label="Tên studio của bạn (có thể đổi sau)" icon="fa-palette" value="{{ old('studio_name') }}" placeholder="VD: Mây Studio" />
        <x-input name="phone" label="Số điện thoại (dùng để đăng nhập)" icon="fa-phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" />
        <x-input name="password" type="password" label="Mật khẩu (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Nhập lại mật khẩu" icon="fa-lock" required autocomplete="new-password" />

        <x-button icon="fa-user-plus" class="mt-2">Tạo tài khoản</x-button>
    </form>
@endsection
