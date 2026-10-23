{{-- Mẫu "Tối giản": ảnh bìa rộng, ảnh đại diện đè mép, chữ gọn. --}}
<header class="pf-hero mb-10">
    @if($studio->coverUrl())
        <img src="{{ $studio->coverUrl() }}" alt="" class="w-full aspect-[16/7] object-cover rounded-xl">
    @endif
    <div class="{{ $studio->coverUrl() ? '-mt-8 px-5' : '' }}">
        @include('public._avatar', ['size' => 'w-16 h-16 ring-4 ring-[var(--paper)]'])
    </div>
    <h1 class="pf-name font-display text-4xl md:text-5xl font-bold tracking-tight mt-4">{{ $studio->name }}</h1>
    @if($headline)<p class="pf-headline text-xl md:text-2xl text-[var(--ink-2)] mt-2 max-w-2xl leading-snug">{{ $headline }}</p>@endif
    @if($studio->bio)<p class="mt-4 text-[var(--ink-2)] leading-relaxed whitespace-pre-line max-w-2xl">{{ $studio->bio }}</p>@endif
    @include('public._hero-actions')
</header>
@include('public._portfolio')
