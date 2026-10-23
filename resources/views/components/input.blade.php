@props(['label' => null, 'icon' => null, 'name', 'type' => 'text', 'hint' => null, 'id' => null])
@php $fieldId = $id ?? str_replace(['[', ']'], ['_', ''], $name); @endphp
<div>
    @if($label)
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">{{ $label }}</label>
    @endif
    <div class="relative">
        @if($icon)
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm pointer-events-none"><i class="fa-solid {{ $icon }}"></i></span>
        @endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $fieldId }}"
            @error($name) aria-invalid="true" @enderror
            {{ $attributes->merge([
                'autocomplete' => 'off',
                'class' => 'w-full rounded-lg border bg-white text-[var(--ink)] placeholder-stone-400 py-2.5 text-[0.95rem] transition focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)] '
                    .($errors->has($name) ? 'border-red-400 ' : 'border-[var(--line-strong)] ')
                    .($icon ? 'pl-10 pr-3.5' : 'px-3.5'),
            ]) }}>
    </div>
    @if($hint)<p class="mt-1 text-xs text-[var(--muted)]">{{ $hint }}</p>@endif
    @error($name)
        <p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $message }}</p>
    @enderror
</div>
