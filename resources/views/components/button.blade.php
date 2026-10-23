@props(['variant' => 'primary', 'icon' => null, 'size' => 'md', 'href' => null])
@php
    $sizes = ['sm' => 'px-3 py-1.5 text-sm rounded-md', 'md' => 'px-4 py-2.5 text-[0.95rem] rounded-lg', 'lg' => 'px-5 py-3 text-base rounded-lg'];
    $variants = [
        'primary' => 'bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-[var(--on-accent)]',
        'ink' => 'bg-[var(--ink)] hover:bg-black text-white',
        'outline' => 'bg-white border border-[var(--line-strong)] hover:border-[var(--ink)] text-[var(--ink)]',
        'ghost' => 'hover:bg-stone-100 text-[var(--ink-2)]',
        'danger' => 'bg-white border border-red-200 text-red-600 hover:bg-red-50',
        'success' => 'bg-emerald-600 hover:bg-emerald-700 text-white',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 font-semibold transition disabled:opacity-50 disabled:pointer-events-none whitespace-nowrap '
        .($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@if($icon)<i class="fa-solid {{ $icon }}" aria-hidden="true"></i>@endif{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>@if($icon)<i class="fa-solid {{ $icon }}" aria-hidden="true"></i>@endif{{ $slot }}</button>
@endif
