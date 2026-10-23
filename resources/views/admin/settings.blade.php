@extends('layouts.admin')
@section('title', 'Cài đặt hệ thống · Quản trị Nét')
@section('content')
    <h1 class="font-display text-2xl font-bold tracking-tight mb-6">Cài đặt hệ thống</h1>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-xl bg-white rounded-lg border border-[var(--line)] p-4 sm:p-5 space-y-4">
        @csrf @method('PUT')

        <div>
            <p class="text-sm font-semibold mb-1">Đăng ký tài khoản mới</p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="registration_open" value="0">
                <input type="checkbox" name="registration_open" value="1" @checked($registrationOpen) class="mt-1 w-4 h-4 rounded border-neutral-300">
                <span class="text-sm text-[var(--ink-2)] leading-relaxed">
                    Cho phép người lạ tự đăng ký
                    <span class="block text-xs text-[var(--muted)] mt-0.5">
                        Tắt nếu bạn chỉ tự dùng hoặc dùng trong nhóm nhỏ. Khi tắt: trang đăng ký bị vô hiệu hoá, đăng nhập Google chỉ dành cho tài khoản đã có trong hệ thống. Tài khoản hiện có vẫn đăng nhập bình thường.
                    </span>
                </span>
            </label>
        </div>

        <x-button variant="ink" icon="fa-floppy-disk">Lưu cài đặt</x-button>
    </form>
@endsection
