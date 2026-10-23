{{-- Mẫu "Tạp chí": măng-sét như bìa tạp chí, tên studio chữ có chân cực lớn, câu chào in nghiêng, giới thiệu chia 2 cột có chữ hoa đầu đoạn. --}}
<header class="pf-hero mb-14">
    <div class="pf-ed-masthead">
        <span class="truncate">{{ $studio->tagline ?: 'Studio thiết kế' }}</span>
        <span class="tnum">Số {{ now()->format('m') }} · {{ now()->year }}</span>
        <span class="truncate">{{ '@'.$studio->slug }}</span>
    </div>
    <h1 class="pf-name font-display">{{ $studio->name }}</h1>
    @if($studio->page_headline)<p class="pf-headline">{{ $studio->page_headline }}</p>@endif
    <div class="pf-ed-byline">
        @include('public._avatar', ['size' => 'w-10 h-10'])
        <span>Ấn phẩm của <strong>{{ $studio->name }}</strong>@if($services->isNotEmpty()) · {{ $services->count() }} dịch vụ @endif @if($works->isNotEmpty()) · {{ $works->count() }} tác phẩm @endif</span>
    </div>
    @if($studio->coverUrl())
        <figure class="pf-ed-cover">
            <img src="{{ $studio->coverUrl() }}" alt="">
            <figcaption>Ảnh bìa — {{ $studio->name }}</figcaption>
        </figure>
    @endif
    @if($studio->bio)<p class="pf-ed-bio whitespace-pre-line">{{ $studio->bio }}</p>@endif
    @include('public._hero-actions')
</header>
@include('public._portfolio')
