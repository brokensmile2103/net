{{-- Mẫu "Sổ phác thảo": tên viết tay gạch chân bút dạ, câu chào trên mẩu giấy note, ảnh dán băng keo. --}}
<header class="pf-hero mb-12">
    <div class="flex items-center gap-4">
        @include('public._avatar', ['size' => 'w-16 h-16'])
        <h1 class="pf-name font-display">{{ $studio->name }}</h1>
    </div>
    @if($headline)<p class="pf-note mt-6">{{ $headline }}</p>@endif
    @if($studio->bio)<p class="mt-6 leading-relaxed whitespace-pre-line max-w-2xl">{{ $studio->bio }}</p>@endif
    @include('public._hero-actions')
    @if($studio->coverUrl())
        <div class="pf-taped mt-10"><img src="{{ $studio->coverUrl() }}" alt="" class="w-full aspect-[16/8] object-cover"></div>
    @endif
</header>
@include('public._portfolio')
