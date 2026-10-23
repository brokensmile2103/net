{{-- Mẫu "Thẻ màu": tên studio in trên thẻ màu như quạt màu Pantone (mã HEX thật của màu nhấn), kèm dải sắc độ; ảnh & tác phẩm là các thẻ màu có nhãn. --}}
@php $hex = strtoupper($studio->accentHex()); @endphp
<header class="pf-hero mb-14">
    <div class="pf-sw-hero">
        <div class="pf-sw-chip pf-sw-main">
            <div class="pf-sw-color">
                @include('public._avatar', ['size' => 'w-14 h-14'])
                <span class="pf-sw-code" aria-hidden="true">{{ $hex }}</span>
            </div>
            <div class="pf-sw-label">
                <h1 class="pf-name font-display">{{ $studio->name }}</h1>
                <p class="pf-sw-meta">NÉT {{ ltrim($hex, '#') }} C · {{ $studio->slug }}</p>
            </div>
        </div>
        <div class="pf-sw-side">
            @if($headline)<p class="pf-headline">{{ $headline }}</p>@endif
            @if($studio->bio)<p class="mt-4 leading-relaxed whitespace-pre-line text-[var(--ink-2)]">{{ $studio->bio }}</p>@endif
            <ol class="pf-sw-tints" aria-hidden="true">
                @foreach([100, 80, 60, 40, 20] as $t)<li style="--t: {{ $t }}%"><span>{{ $t }}</span></li>@endforeach
            </ol>
            @include('public._hero-actions')
        </div>
    </div>
    @if($studio->coverUrl())
        <figure class="pf-sw-chip pf-sw-cover mt-10">
            <img src="{{ $studio->coverUrl() }}" alt="" class="w-full aspect-[16/7] object-cover">
            <figcaption class="pf-sw-label"><strong>{{ $studio->name }}</strong><span>Ảnh bìa</span></figcaption>
        </figure>
    @endif
</header>
@include('public._portfolio')
