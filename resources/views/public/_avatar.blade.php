{{-- Ảnh đại diện studio (hoặc chữ viết tắt). Biến: $studio, $size (class kích thước) --}}
@if($studio->avatarUrl())
    <img src="{{ $studio->avatarUrl() }}" alt="{{ $studio->name }}" class="pf-avatar {{ $size ?? 'w-16 h-16' }} rounded-full object-cover">
@else
    <span class="pf-avatar {{ $size ?? 'w-16 h-16' }} rounded-full bg-[var(--accent)] text-[var(--on-accent)] flex items-center justify-center font-display font-bold text-xl" aria-hidden="true">{{ $studio->initials() }}</span>
@endif
