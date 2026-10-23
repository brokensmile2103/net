@props(['text', 'title' => null])
{{-- Khối văn bản + nút "Sao chép" (dán Zalo/Messenger/email). --}}
<div x-data="{ copied: false }" {{ $attributes->merge(['class' => 'rounded-lg border border-[var(--line)] bg-white p-4']) }}>
    <div class="flex items-center justify-between gap-2 mb-2">
        <div class="min-w-0 text-sm font-semibold text-[var(--ink)]">{{ $title ?? '' }}</div>
        <button type="button" @click="copyText($refs.t.innerText).then(() => { copied = true; setTimeout(() => copied = false, 1500) }).catch(() => alert('Không sao chép được — hãy bôi đen đoạn văn bản rồi sao chép thủ công.'))"
                class="shrink-0 text-xs px-3 py-1.5 rounded-md font-semibold transition" :class="copied ? 'bg-emerald-600 text-white' : 'bg-[var(--accent-soft)] text-[var(--accent-ink)]'">
            <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i> <span x-text="copied ? 'Đã chép' : 'Sao chép'">Sao chép</span>
        </button>
    </div>
    <pre x-ref="t" class="whitespace-pre-wrap [overflow-wrap:anywhere] font-sans text-sm text-[var(--ink-2)] leading-relaxed">{{ $text }}</pre>
</div>
