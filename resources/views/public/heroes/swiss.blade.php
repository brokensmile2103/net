{{-- Mẫu "Lưới Thuỵ Sĩ": khối màu nhấn bên trái, tên studio cỡ cực lớn căn trái, lưới 12 cột. --}}
<header class="pf-hero mb-14">
    <div class="pf-swiss-grid">
        <div class="pf-swiss-block">
            @include('public._avatar', ['size' => 'w-14 h-14'])
            @if($studio->tagline)<p class="mt-auto pt-10 text-base font-semibold leading-snug">{{ $studio->tagline }}</p>@endif
        </div>
        <h1 class="pf-name font-display">{{ $studio->name }}</h1>
    </div>
    <div class="pf-swiss-grid mt-8">
        <div class="pf-swiss-side text-sm">
            @if($services->isNotEmpty())<p>{{ $services->count() }} dịch vụ</p>@endif
            @if($works->isNotEmpty())<p>{{ $works->count() }} tác phẩm</p>@endif
        </div>
        <div class="pf-swiss-main">
            @if($studio->page_headline)<p class="pf-headline">{{ $studio->page_headline }}</p>@endif
            @if($studio->bio)<p class="mt-5 leading-relaxed whitespace-pre-line max-w-2xl">{{ $studio->bio }}</p>@endif
            @include('public._hero-actions')
        </div>
    </div>
    @if($studio->coverUrl())
        <img src="{{ $studio->coverUrl() }}" alt="" class="w-full aspect-[16/7] object-cover mt-12">
    @endif
</header>
@include('public._portfolio')
