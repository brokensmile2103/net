{{-- Hình thu nhỏ của 1 mẫu trang đặt đơn (vẽ bằng CSS, ăn theo màu nhấn đang chọn). Biến: $key --}}
<span class="block aspect-[16/10] w-full overflow-hidden rounded-md ring-1 ring-[var(--line)] relative" aria-hidden="true">
    @switch($key)
        @case('artboard')
            <span class="absolute inset-0" style="background:#2c2c30 radial-gradient(#45454b 1px, transparent 1px) 0 0/8px 8px"></span>
            <span class="absolute inset-x-0 top-0 h-[7px]" style="background:#1f1f22 repeating-linear-gradient(90deg,#66666d 0 1px,transparent 1px 6px)"></span>
            <span class="absolute left-[14%] right-[14%] top-[22%] bottom-0 bg-white"></span>
            <span class="absolute left-[20%] top-[34%] px-1 text-[13px] font-bold leading-tight" style="font-family:'Space Grotesk Variable';outline:1.5px solid var(--accent)">Aa Studio</span>
            <span class="absolute left-[20%] top-[62%] w-[36%] h-[6px] rounded-sm" style="background:var(--accent)"></span>
            @break
        @case('riso')
            <span class="absolute inset-0" style="background:#f1efe9"></span>
            <span class="absolute left-[10%] top-[16%] text-[30px] font-extrabold leading-none" style="font-family:'Unbounded Variable';color:var(--riso-face,var(--accent));text-shadow:2px 2px 0 var(--riso-shadow,var(--ink-riso))">Aa</span>
            <span class="absolute left-[10%] top-[62%] w-[55%] h-[9px]" style="background:var(--ink-riso)"></span>
            <span class="absolute right-[10%] top-[18%] w-[26%] h-[48%] rounded-lg border-2 border-[#201e2c]" style="background:var(--accent);box-shadow:3px 3px 0 var(--ink-riso)"></span>
            @break
        @case('swiss')
            <span class="absolute inset-0 bg-white"></span>
            <span class="absolute left-[8%] top-[12%] w-[30%] h-[54%]" style="background:var(--accent)"></span>
            <span class="absolute left-[44%] top-[22%] text-[34px] font-extrabold leading-[.85] tracking-tighter text-black" style="font-family:'Archivo Variable';font-stretch:72%">Aa<br>Std</span>
            <span class="absolute left-[8%] right-[8%] top-[76%] h-[2px] bg-black"></span>
            @break
        @case('sketch')
            <span class="absolute inset-0" style="background:#fcfcfa;background-image:linear-gradient(#dfe7f0 1px,transparent 1px),linear-gradient(90deg,#dfe7f0 1px,transparent 1px);background-size:9px 9px"></span>
            <span class="absolute left-[10%] top-[14%] text-[24px] leading-none px-0.5 text-[#2a2d34]" style="font-family:'Patrick Hand';background:linear-gradient(176deg,transparent 58%,color-mix(in srgb,var(--accent) 40%,transparent) 58%)">Aa Studio</span>
            <span class="absolute left-[12%] top-[52%] w-[34%] h-[32%] bg-white shadow -rotate-3"><span class="absolute -top-1 left-1/2 -translate-x-1/2 w-6 h-2 rotate-[-4deg]" style="background:rgba(246,224,132,.85)"></span></span>
            <span class="absolute left-[54%] top-[56%] w-[32%] h-[28%] bg-white shadow rotate-2"></span>
            @break
        @case('editorial')
            <span class="absolute inset-0" style="background:#f5f0e6"></span>
            <span class="absolute left-[8%] right-[8%] top-[10%] h-[9%] border-y border-[#1c1a16]"></span>
            <span class="absolute inset-x-0 top-[26%] text-center text-[25px] leading-none text-[#1c1a16]" style="font-family:'Fraunces Variable',Georgia,serif;font-weight:560;letter-spacing:-.03em">Studio</span>
            <span class="absolute inset-x-0 top-[52%] text-center text-[10px] italic text-[#3b3830]" style="font-family:'Fraunces Variable',Georgia,serif">“câu chào”</span>
            <span class="absolute left-[8%] right-[8%] top-[68%] bottom-[8%]" style="background:linear-gradient(135deg,var(--accent-soft),var(--accent))"></span>
            @break
        @case('swatch')
            <span class="absolute inset-0" style="background:#e9e9e6"></span>
            <span class="absolute left-[10%] top-[12%] w-[44%] h-[78%] bg-white rounded-sm shadow -rotate-3 overflow-hidden"><span class="block h-[68%]" style="background:var(--accent)"></span><span class="block px-1 pt-0.5 text-[9px] font-extrabold leading-tight text-[#161618]" style="font-family:'Archivo Variable';font-stretch:85%">Studio</span></span>
            <span class="absolute left-[60%] right-[8%] top-[30%] grid grid-cols-3 gap-[2px] p-[2px] bg-white rounded-sm shadow">
                <span class="aspect-square" style="background:var(--accent)"></span><span class="aspect-square" style="background:color-mix(in srgb,var(--accent) 60%,#fff)"></span><span class="aspect-square" style="background:color-mix(in srgb,var(--accent) 25%,#fff)"></span>
            </span>
            <span class="absolute left-[60%] top-[62%] w-[22%] h-[7px] rounded-sm" style="background:var(--accent)"></span>
            @break
        @default
            <span class="absolute inset-0" style="background:#f4f4f2"></span>
            <span class="absolute left-[10%] right-[10%] top-[12%] h-[34%] rounded" style="background:var(--accent-soft)"></span>
            <span class="absolute left-[14%] top-[38%] w-5 h-5 rounded-full ring-2 ring-[#f4f4f2]" style="background:var(--accent)"></span>
            <span class="absolute left-[10%] top-[62%] text-[15px] font-bold font-display text-[#1d1d22]">Aa Studio</span>
            <span class="absolute left-[10%] top-[82%] w-[24%] h-[7px] rounded-sm" style="background:var(--accent)"></span>
    @endswitch
</span>
