{{-- Cài đặt → Email gửi khách. Biến: $studio, $box --}}
@php
    $ep = $studio->emailPrefs();
    $mailer = app(\App\Services\Mail\StudioMailer::class);
    $platform = app(\App\Services\Mail\PlatformMail::class);
    $mailReady = \App\Services\ReminderService::mailReady();
    [$fromAddress] = $platform->fromFor($studio);
    $used = $mailer->usedToday($studio);
    $limit = $platform->studioLimit($studio);
    $recent = $studio->emailLogs()->with('order:id,code')->latest('id')->limit(6)->get();
    $themeLabel = config('net.page_themes.'.$studio->pageTheme().'.label');
@endphp
<section id="email-khach" class="{{ $box }}" aria-labelledby="h-email" x-data="{ stagesOn: @js((bool) old('stages_on', $ep['stages_on'])), sandbox: @js((bool) old('sandbox', $ep['sandbox'])) }">
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="h-email" class="font-display text-lg font-bold tracking-tight">Email gửi khách</h2>
        @if(! $mailReady)
            <span class="text-xs font-semibold rounded-full px-2 py-0.5 bg-amber-50 text-amber-800">Máy chủ chưa gửi được email</span>
        @elseif($ep['blocked'])
            <span class="text-xs font-semibold rounded-full px-2 py-0.5 bg-red-50 text-red-700">Quản trị viên đang tạm dừng</span>
        @else
            <span class="text-xs font-semibold rounded-full px-2 py-0.5" :class="sandbox ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700'" x-text="sandbox ? 'Chế độ thử' : 'Sẵn sàng'">{{ $ep['sandbox'] ? 'Chế độ thử' : 'Sẵn sàng' }}</span>
        @endif
    </div>
    <p class="text-sm text-[var(--muted)] mt-0.5">Nét gửi email cho khách bằng máy chủ thư của Nét (tên miền đã xác thực — bạn không cần tên miền hay tài khoản Resend riêng). Khách thấy người gửi là <span class="font-semibold text-[var(--ink-2)]">{{ $ep['sender_name'] }}</span>, bấm trả lời là thư về email của bạn. Giao diện email đi theo mẫu <span class="font-semibold text-[var(--ink-2)]">{{ $themeLabel }}</span> và màu nhấn của trang đặt đơn.</p>

    @unless($mailReady)
        <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
            <i class="fa-solid fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>Người quản trị Nét cần cấu hình gửi email (Siêu quản trị → Cài đặt → Email) — tới lúc đó Nét chưa gửi được email nào. Bạn vẫn lưu cài đặt và xem trước được.
        </div>
    @endunless

    {{-- Hạn mức riêng của studio --}}
    <div class="mt-4 rounded-lg bg-stone-50 border border-[var(--line)] p-4">
        @php $pct = $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 100; @endphp
        <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
            <span class="font-semibold">Hôm nay đã gửi</span>
            <span class="tnum text-[var(--muted)]">{{ $used }} / {{ $limit }} email</span>
        </div>
        <div class="h-1.5 rounded-full bg-white ring-1 ring-[var(--line)] mt-2" aria-hidden="true"><div class="h-full rounded-full {{ $pct >= 100 ? 'bg-red-400' : 'bg-[var(--accent)]' }}" style="width: {{ $pct }}%"></div></div>
        <p class="text-xs text-[var(--muted)] mt-2">Hạn mức riêng của studio bạn (tính cả email nhắc, báo tiến độ, xác nhận hẹn, email thử) — đặt lại lúc 0h. Hết hạn mức thì email nhắc để mai gửi, email tiến độ được ghi “bỏ qua” trong nhật ký. Địa chỉ gửi: <span class="font-mono">{{ $fromAddress }}</span></p>
    </div>

    <form method="POST" action="{{ route('settings.email') }}" class="mt-5 space-y-5" autocomplete="off">
        @csrf @method('PUT')
        <div class="grid sm:grid-cols-2 gap-4">
            <x-input name="sender_name" label="Tên người gửi" :value="old('sender_name', $ep['sender_name'])" maxlength="80" autocomplete="off" data-1p-ignore data-lpignore="true" :placeholder="$studio->name" hint="Hiện ở hộp thư của khách. Mặc định là tên studio." />
            <x-input name="reply_to" type="email" label="Khách trả lời thư về" :value="old('reply_to', $ep['reply_to'])" maxlength="255" autocomplete="off" data-1p-ignore data-lpignore="true" :placeholder="$studio->email ?: 'ban@gmail.com'" hint="Mặc định là email ở Hồ sơ studio." />
            <div class="sm:col-span-2"><x-input name="signature" label="Chữ ký cuối thư (tuỳ chọn)" :value="old('signature', $ep['signature'])" maxlength="300" autocomplete="off" data-1p-ignore data-lpignore="true" placeholder="Linh · Thiết kế logo & nhận diện · behance.net/linh" /></div>
        </div>

        <input type="hidden" name="sandbox" value="0">
        <label class="flex items-start gap-2.5 rounded-lg border border-sky-200 bg-sky-50/60 px-4 py-3" for="email-sandbox">
            <input id="email-sandbox" type="checkbox" name="sandbox" value="1" x-model="sandbox" class="mt-1 w-4 h-4 accent-[var(--accent)]">
            <span class="text-sm"><span class="font-semibold">Chế độ thử (sandbox)</span>
                <span class="block text-[var(--muted)]">Mọi email lẽ ra gửi khách được chuyển về <span class="font-semibold text-[var(--ink-2)]">{{ $studio->email ?: 'email studio (chưa điền ở Hồ sơ studio)' }}</span>, gắn nhãn “Email thử” — khách không nhận gì. Bật khi mới cài đặt để xem email thật trông ra sao.</span></span>
        </label>

        {{-- Email theo giai đoạn --}}
        <div>
            <input type="hidden" name="stages_on" value="0">
            <label class="flex items-start gap-2.5" for="email-stages-on">
                <input id="email-stages-on" type="checkbox" name="stages_on" value="1" x-model="stagesOn" class="mt-1 w-4 h-4 accent-[var(--accent)]">
                <span class="text-sm"><span class="font-semibold">Tự gửi email báo tiến độ theo từng giai đoạn</span>
                    <span class="block text-[var(--muted)]">Khách nhận email đẹp mỗi khi đơn sang giai đoạn mới. Gửi sau {{ config('net.stage_emails.delay_minutes') }} phút — kéo nhầm thẻ rồi Hoàn tác thì email chưa đi. Chỉ gửi cho khách có email; khách tắt được bằng link cuối thư.</span></span>
            </label>
            <div class="mt-3 rounded-lg border border-[var(--line)] divide-y divide-[var(--line)]" :class="stagesOn ? '' : 'opacity-60'">
                @foreach(config('net.stage_emails.stages') as $k => $def)
                    @php $st = $ep['stages'][$k]; @endphp
                    <div class="px-4 py-3" x-data="{ open: @js($st['note'] !== '' || $errors->has('stages.'.$k.'.note')) }">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                            <input type="hidden" name="stages[{{ $k }}][on]" value="0">
                            <label class="flex items-start gap-2.5 flex-1 min-w-56" for="st-{{ $k }}">
                                <input id="st-{{ $k }}" type="checkbox" name="stages[{{ $k }}][on]" value="1" class="mt-1 w-4 h-4 accent-[var(--accent)]" @checked(old('stages.'.$k.'.on', $st['on']))>
                                <span class="text-sm"><span class="font-medium">{{ $def['label'] }}</span><span class="block text-xs text-[var(--muted)]">{{ $def['hint'] }}</span></span>
                            </label>
                            <button type="button" class="text-xs text-[var(--muted)] hover:text-[var(--ink)]" @click="open = !open" :aria-expanded="open.toString()"><i class="fa-regular fa-message mr-1" aria-hidden="true"></i>Lời nhắn</button>
                            <a href="{{ route('settings.email.preview', ['loai' => 'stage:'.$k]) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-[var(--accent-ink)] hover:underline">Xem trước</a>
                        </div>
                        <div x-show="open" x-cloak class="mt-2 pl-6">
                            <label class="sr-only" for="st-{{ $k }}-note">Lời nhắn thêm cho {{ $def['label'] }}</label>
                            <textarea id="st-{{ $k }}-note" name="stages[{{ $k }}][note]" rows="2" maxlength="500" placeholder="Lời nhắn riêng hiện trong khung nổi bật của email (không bắt buộc)" class="w-full rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">{{ old('stages.'.$k.'.note', $st['note']) }}</textarea>
                            @error('stages.'.$k.'.note')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-[var(--muted)] mt-2">Email nhắc khách (phương án chờ xem, báo giá, thanh toán) cài ở mục <a href="#nhac-khach" class="underline">Nhắc khách tự động</a> — dùng chung giao diện này. Xem trước: <a href="{{ route('settings.email.preview', ['loai' => 'reminder:review']) }}" target="_blank" rel="noopener" class="underline">nhắc xem phương án</a> · <a href="{{ route('settings.email.preview', ['loai' => 'reminder:payment']) }}" target="_blank" rel="noopener" class="underline">nhắc thanh toán</a>.</p>
        </div>

        <x-button icon="fa-check">Lưu cài đặt email</x-button>
    </form>

    <form method="POST" action="{{ route('settings.email.test') }}" class="mt-4 flex flex-wrap items-center gap-2 text-sm">@csrf
        <label for="email-test-stage" class="text-[var(--muted)]">Gửi thử email</label>
        <select id="email-test-stage" name="stage" class="rounded-md border border-[var(--line-strong)] px-2 py-1.5 text-sm">
            @foreach(config('net.stage_emails.stages') as $k => $def)<option value="{{ $k }}" @selected($k === 'review')>{{ $def['label'] }}</option>@endforeach
        </select>
        <button class="font-semibold text-[var(--accent-ink)] hover:underline" @disabled(! $studio->email)><i class="fa-regular fa-paper-plane mr-1" aria-hidden="true"></i>tới {{ $studio->email ?: 'email studio (chưa điền)' }}</button>
    </form>

    @if($recent->isNotEmpty())
        <div class="mt-5">
            <div class="flex items-baseline justify-between gap-2">
                <p class="text-sm font-semibold">Gần đây</p>
                <a href="{{ route('settings.email.log') }}" class="text-xs font-semibold text-[var(--accent-ink)] hover:underline">Xem nhật ký</a>
            </div>
            <ul class="mt-2 divide-y divide-[var(--line)] text-sm">
                @foreach($recent as $log)
                    @php [$label, $cls] = $log->statusLabel(); @endphp
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 py-1.5">
                        <span class="text-xs tnum text-[var(--muted)] w-20">{{ ($log->sent_at ?? $log->created_at)->format('d/m H:i') }}</span>
                        <span class="flex-1 min-w-40 truncate">{{ $log->kindLabel() }}@if($log->order) · {{ $log->order->code }}@endif <span class="text-[var(--muted)]">→ {{ $log->to_email }}</span></span>
                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $cls }}" @if($log->note) title="{{ $log->note }}" @endif>{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
