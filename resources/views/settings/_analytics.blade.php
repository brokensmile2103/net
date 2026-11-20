{{-- Cài đặt → Google Analytics của riêng studio. Biến: $studio, $box --}}
<section id="trang-dat-don-ga" class="{{ $box }}" aria-labelledby="h-ga">
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="h-ga" class="font-display text-lg font-bold tracking-tight">Google Analytics</h2>
        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $studio->gaId() ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600' }}">{{ $studio->gaId() ? 'Đang gắn '.$studio->gaId() : 'Chưa gắn' }}</span>
    </div>
    <p class="text-sm text-[var(--muted)] mt-0.5">Nét đã có sẵn <a href="{{ route('analytics.index') }}" class="font-semibold text-[var(--accent-ink)] hover:underline">Thống kê trang đặt đơn</a>. Cần sâu hơn (khu vực, thời gian xem, đo quảng cáo Facebook/Google)? Gắn GA4 của riêng bạn — chỉ cần <span class="font-semibold text-[var(--ink-2)]">mã đo lường</span>, Nét tự tạo đoạn mã nên không lo chèn nhầm mã lạ vào trang.</p>

    <form method="POST" action="{{ route('settings.analytics') }}" class="mt-4 flex flex-wrap items-end gap-3">
        @csrf @method('PUT')
        <div class="flex-1 min-w-56">
            <x-input name="ga_measurement_id" label="Mã đo lường (Measurement ID)" :value="old('ga_measurement_id', $studio->ga_measurement_id)" placeholder="G-XXXXXXXXXX" maxlength="5000" autocomplete="off" spellcheck="false" />
        </div>
        <x-button icon="fa-check">Lưu</x-button>
        @if($studio->gaId())
            <button type="submit" name="remove" value="1" class="text-sm text-[var(--muted)] hover:text-red-600 pb-2.5">Gỡ</button>
        @endif
    </form>
    @error('ga_measurement_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

    <details class="mt-4 text-sm">
        <summary class="cursor-pointer font-semibold text-[var(--ink-2)]">Lấy mã G-… ở đâu? Nét gửi gì cho Google?</summary>
        <ol class="mt-2 ml-5 list-decimal space-y-1 text-[var(--ink-2)]">
            <li>Mở <a href="https://analytics.google.com" target="_blank" rel="noopener" class="text-[var(--accent-ink)] font-semibold hover:underline">analytics.google.com</a> → Quản trị → Tạo thuộc tính → Luồng dữ liệu <span class="font-semibold">Web</span>, nhập địa chỉ <span class="font-mono text-xs">{{ parse_url(url('/'), PHP_URL_HOST) }}</span>.</li>
            <li>Sao chép <span class="font-semibold">Mã đo lường</span> (dạng <span class="font-mono">G-XXXXXXXXXX</span>) dán vào ô trên. Lỡ dán cả đoạn mã gtag cũng được — Nét tự lấy mã ra.</li>
        </ol>
        <ul class="mt-3 ml-5 list-disc space-y-1 text-[var(--muted)]">
            <li>Trang đặt đơn: gửi lượt xem như bình thường.</li>
            <li>Khi khách gửi đơn hoặc đặt hẹn: gửi sự kiện <span class="font-mono text-xs">generate_lead</span> (đánh dấu là “Chuyển đổi chính” trong GA để đo quảng cáo). Link theo dõi đơn riêng của khách <span class="font-semibold">không</span> được gửi cho Google.</li>
            <li>Bạn mở trang của mình khi đang đăng nhập Nét: không tính.</li>
            <li>GA dùng cookie của Google — nên ghi 1 dòng trong quy định làm việc nếu khách của bạn quan tâm quyền riêng tư.</li>
        </ul>
    </details>
</section>
