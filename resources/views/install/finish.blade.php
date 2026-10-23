@extends('layouts.guest')
@section('title', 'Cài đặt Nét hoàn tất')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Xong rồi!')
@section('content')
    <div class="text-center mb-5">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[var(--accent-soft)] text-[var(--accent-ink)] text-3xl mb-4">
            <i class="fa-solid fa-check"></i>
        </div>
        <p class="text-sm text-[var(--ink-2)] leading-relaxed">
            Đã khởi tạo xong <strong>Nét</strong>. Tài khoản của bạn vừa là Super Admin vừa là chủ studio đầu tiên — vào làm việc được ngay. Freelancer khác có thể tự đăng ký qua <strong>/dang-ky</strong>; nếu chỉ tự dùng, hãy đóng đăng ký trong <strong>Cài đặt hệ thống</strong>.
        </p>
    </div>
    <a href="{{ route('today') }}">
        <x-button variant="success" icon="fa-house">Vào khu làm việc</x-button>
    </a>
    <a href="{{ route('admin.settings.edit') }}" class="block text-center text-sm text-[var(--muted)] hover:underline mt-3">Cài đặt hệ thống (đóng/mở đăng ký)</a>
@endsection
