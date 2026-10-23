@extends('layouts.admin')
@section('title', 'Sửa trang: '.$page->title.' · Quản trị Nét')
@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
        <h1 class="text-xl font-bold text-neutral-900">Sửa nội dung: {{ $page->title }}</h1>
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-neutral-500 hover:text-neutral-700 flex items-center gap-1.5 shrink-0">
            <i class="fa-solid fa-arrow-left"></i>Quay lại
        </a>
    </div>

    <form method="POST" action="{{ route('admin.pages.update', $page->slug) }}" class="bg-white rounded-lg border border-neutral-200 p-4 sm:p-5 space-y-4">
        @csrf @method('PUT')

        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div>
            <label for="page-title" class="block text-sm font-medium text-neutral-700 mb-1.5">Tiêu đề trang</label>
            <input type="text" name="title" id="page-title" value="{{ old('title', $page->title) }}" required autocomplete="off"
                   class="w-full rounded-lg border border-neutral-300 py-2.5 px-4 text-sm focus:border-neutral-500 focus:outline-none">
        </div>

        <div>
            <label for="page-content" class="block text-sm font-medium text-neutral-700 mb-1.5">Nội dung (hỗ trợ HTML — dùng &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;, &lt;a href=""&gt;)</label>
            <textarea name="content" id="page-content" rows="18" autocomplete="off"
                      class="w-full rounded-lg border border-neutral-300 py-2.5 px-4 text-sm font-mono focus:border-neutral-500 focus:outline-none">{{ old('content', $page->content) }}</textarea>
        </div>

        <div>
            <label for="page-meta-description" class="block text-sm font-medium text-neutral-700 mb-1.5">
                Mô tả SEO (hiện trên kết quả Google &amp; khi share link — tối đa 160 ký tự)
            </label>
            <textarea name="meta_description" id="page-meta-description" rows="2" maxlength="160" autocomplete="off"
                      class="w-full rounded-lg border border-neutral-300 py-2.5 px-4 text-sm focus:border-neutral-500 focus:outline-none"
                      placeholder="Để trống sẽ tự lấy đoạn đầu của nội dung.">{{ old('meta_description', $page->meta_description) }}</textarea>
        </div>

        <div class="flex items-center gap-3">
            <button class="bg-neutral-900 hover:bg-neutral-800 text-white rounded-lg px-5 py-2.5 text-sm font-semibold transition">
                <i class="fa-solid fa-floppy-disk mr-1.5"></i>Lưu thay đổi
            </button>
            <a href="{{ route('pages.'.($page->slug === 'gioi-thieu' ? 'about' : ($page->slug === 'dieu-khoan-su-dung' ? 'terms' : 'privacy'))) }}"
               target="_blank" class="text-sm text-neutral-500 hover:text-neutral-700">
                <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Xem trang thật
            </a>
        </div>
    </form>
@endsection
