{{-- Mẫu "In Risograph": tên chữ béo in chồng 2 màu mực, câu chào trên dải mực thứ 2, ảnh bìa in duotone. --}}
<header class="pf-hero mb-12">
    <div class="flex items-center gap-3">
        @include('public._avatar', ['size' => 'w-14 h-14'])
        @if($studio->tagline)<p class="text-sm font-semibold max-w-xs">{{ $studio->tagline }}</p>@endif
    </div>
    <h1 class="pf-name font-display mt-6">{{ $studio->name }}</h1>
    @if($studio->page_headline)<p class="pf-headline mt-6">{{ $studio->page_headline }}</p>@endif
    @if($studio->bio)<p class="mt-6 leading-relaxed whitespace-pre-line max-w-2xl">{{ $studio->bio }}</p>@endif
    @include('public._hero-actions')
    @if($studio->coverUrl())
        <div class="pf-duotone mt-10"><img src="{{ $studio->coverUrl() }}" alt=""></div>
    @endif
</header>
@include('public._portfolio')
