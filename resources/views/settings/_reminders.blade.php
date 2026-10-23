{{-- Cài đặt → Nhắc khách tự động. Biến: $studio, $box --}}
@php
    $rp = $studio->reminderPrefs();
    $mailReady = \App\Services\ReminderService::mailReady();
    $withEmail = $studio->clients()->whereNotNull('email')->where('email', '!=', '')->get(['id', 'email']);
    $plausible = $withEmail->filter(fn ($c) => \App\Support\EmailCheck::plausible($c->email))->count();
    $hours = config('net.reminders.hours');
@endphp
<section id="nhac-khach" class="{{ $box }}" aria-labelledby="h-remind" x-data="{ on: @js((bool) old('enabled', $rp['enabled'])) }">
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="h-remind" class="font-display text-lg font-bold tracking-tight">Nhắc khách tự động</h2>
        <span class="text-xs font-semibold rounded-full px-2 py-0.5" :class="on ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600'" x-text="on ? 'Đang bật' : 'Đang tắt'">{{ $rp['enabled'] ? 'Đang bật' : 'Đang tắt' }}</span>
    </div>
    <p class="text-sm text-[var(--muted)] mt-0.5">Nét tự gửi email lịch sự nhắc khách khi đơn đứng chờ họ — bạn khỏi phải nhắn “anh/chị xem giúp em nhé”. Chỉ gửi cho khách có email <span class="font-semibold text-[var(--ink-2)]">có vẻ thật</span> (Nét lọc email sai, email mẫu, gõ nhầm tên miền, hộp thư tạm, tên miền không nhận thư), trong khung {{ $hours[0] }}h–{{ $hours[1] }}h. Mỗi email có link để khách tắt nhắc cho đơn đó.</p>

    @unless($mailReady)
        <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
            <i class="fa-solid fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>Máy chủ chưa cấu hình gửi email nên Nét chưa gửi được. Người quản trị cần điền <code class="font-mono text-xs">MAIL_MAILER</code> và <code class="font-mono text-xs">RESEND_API_KEY</code> (hoặc SMTP) trong <code class="font-mono text-xs">.env</code>.
        </div>
    @endunless

    <form method="POST" action="{{ route('settings.reminders') }}" class="mt-5 space-y-4">
        @csrf @method('PUT')
        <input type="hidden" name="enabled" value="0">
        <label class="flex items-start gap-2.5" for="rem-enabled">
            <input id="rem-enabled" type="checkbox" name="enabled" value="1" x-model="on" class="mt-1 w-4 h-4 accent-[var(--accent)]">
            <span class="text-sm"><span class="font-semibold">Bật nhắc khách qua email</span><span class="block text-[var(--muted)]">Hiện có {{ $plausible }}/{{ $withEmail->count() }} khách có email nhận được nhắc.</span></span>
        </label>

        <div class="rounded-lg border border-[var(--line)] divide-y divide-[var(--line)]" :class="on ? '' : 'opacity-60'">
            @foreach(config('net.reminders.kinds') as $k => $kind)
                @php $rule = $rp['kinds'][$k]; @endphp
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                    <input type="hidden" name="kinds[{{ $k }}][on]" value="0">
                    <label class="flex items-start gap-2.5 flex-1 min-w-56" for="rem-{{ $k }}-on">
                        <input id="rem-{{ $k }}-on" type="checkbox" name="kinds[{{ $k }}][on]" value="1" class="mt-1 w-4 h-4 accent-[var(--accent)]" @checked(old('kinds.'.$k.'.on', $rule['on']))>
                        <span class="text-sm"><span class="font-medium">{{ $kind['label'] }}</span><span class="block text-xs text-[var(--muted)]">{{ $kind['hint'] }}</span></span>
                    </label>
                    <label class="flex items-center gap-2 text-sm" for="rem-{{ $k }}-days">
                        Sau
                        <input id="rem-{{ $k }}-days" type="number" name="kinds[{{ $k }}][days]" min="1" max="30" value="{{ old('kinds.'.$k.'.days', $rule['days']) }}" class="w-16 rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-sm tnum">
                        ngày
                    </label>
                </div>
            @endforeach
        </div>

        <label class="flex items-center gap-2 text-sm" for="rem-max">
            Mỗi loại nhắc tối đa
            <input id="rem-max" type="number" name="max" min="1" max="5" value="{{ old('max', $rp['max']) }}" class="w-16 rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-sm tnum">
            lần cho mỗi đơn (các lần cách nhau đúng số ngày ở trên)
        </label>
        <div class="flex flex-wrap items-center gap-3">
            <x-button icon="fa-check">Lưu</x-button>
        </div>
    </form>
    <form method="POST" action="{{ route('settings.reminders.test') }}" class="mt-3">@csrf
        <button class="text-sm font-semibold text-[var(--accent-ink)] hover:underline" @disabled(! $studio->email)><i class="fa-regular fa-paper-plane mr-1" aria-hidden="true"></i>Gửi thử 1 email nhắc tới {{ $studio->email ?: 'email studio (chưa điền)' }}</button>
    </form>
</section>
