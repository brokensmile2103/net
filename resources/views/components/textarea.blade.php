@props(['label' => null, 'name', 'hint' => null, 'rows' => 3, 'value' => null])
@php $fieldId = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); @endphp
<div>
    @if($label)
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $fieldId }}" rows="{{ $rows }}" @error($name) aria-invalid="true" @enderror
        {{ $attributes->except('id')->merge(['class' => 'w-full rounded-lg border bg-white text-[var(--ink)] placeholder-stone-400 py-2.5 px-3.5 text-[0.95rem] leading-relaxed transition focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)] '.($errors->has($name) ? 'border-red-400' : 'border-[var(--line-strong)]')]) }}>{{ $value ?? $slot }}</textarea>
    @if($hint)<p class="mt-1 text-xs text-[var(--muted)]">{{ $hint }}</p>@endif
    @error($name)
        <p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $message }}</p>
    @enderror
</div>
