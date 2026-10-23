{{-- Mẫu "Bàn vẽ": tên studio nằm trong khung chọn (selection box) có tay nắm, ảnh bìa có dấu góc cắt. --}}
<header class="pf-hero mb-10">
    <div class="flex items-center gap-3">
        @include('public._avatar', ['size' => 'w-12 h-12'])
        <p class="pf-layer text-sm">{{ '@'.$studio->slug }}</p>
    </div>
    <h1 class="mt-6">
        <span class="pf-select">
            <span class="pf-name font-display">{{ $studio->name }}</span>
            <span class="pf-handle pf-handle-tl" aria-hidden="true"></span><span class="pf-handle pf-handle-tr" aria-hidden="true"></span>
            <span class="pf-handle pf-handle-bl" aria-hidden="true"></span><span class="pf-handle pf-handle-br" aria-hidden="true"></span>
            <span class="pf-dimension" aria-hidden="true">{{ $services->count() }} dịch vụ × {{ $works->count() }} tác phẩm</span>
        </span>
    </h1>
    @if($headline)<p class="pf-headline text-xl md:text-2xl mt-8 max-w-2xl leading-snug">{{ $headline }}</p>@endif
    @if($studio->bio)<p class="mt-4 leading-relaxed whitespace-pre-line max-w-2xl text-[var(--ink-2)]">{{ $studio->bio }}</p>@endif
    @include('public._hero-actions')
    @if($studio->coverUrl())
        <div class="crop mt-10"><img src="{{ $studio->coverUrl() }}" alt="" class="w-full aspect-[16/8] object-cover"></div>
    @endif
</header>
@include('public._portfolio')
