{{-- Gợi ý "hoặc Ctrl + V để dán ảnh" (JS đổi thành ⌘ trên Mac; ẩn trên máy chỉ có cảm ứng). Xem resources/js/paste-upload.js --}}
@props(['prefix' => 'hoặc'])
<span {{ $attributes->merge(['class' => 'paste-hint']) }} data-paste-hint="{{ $prefix }}">{{ $prefix }} Ctrl+V để dán ảnh</span>
