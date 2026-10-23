{{-- Cài đặt → Hẹn gọi tư vấn (mặc định tắt). Biến: $studio, $box --}}
@php
    $cp = $studio->consultPrefs();
    $oldRanges = old('ranges', $cp['ranges']);
    $ranges = array_pad(array_values((array) $oldRanges), (int) config('net.consult.max_ranges', 3), ['', '']);
    $days = array_map('intval', (array) old('days', $cp['days']));
    $channels = (array) old('channels', $cp['channels']);
    $preview = $cp['enabled'] ? app(\App\Services\ConsultSlots::class)->forStudio($studio) : [];
    $upcoming = $studio->consultations()->where('status', 'booked')->where('starts_at', '>=', now())->count();
@endphp
<section id="tu-van" class="{{ $box }}" aria-labelledby="h-consult" x-data="{ on: @js((bool) old('enabled', $cp['enabled'])) }">
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="h-consult" class="font-display text-lg font-bold tracking-tight">Hẹn gọi tư vấn</h2>
        <span class="text-xs font-semibold rounded-full px-2 py-0.5" :class="on ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600'" x-text="on ? 'Đang bật' : 'Đang tắt'">{{ $cp['enabled'] ? 'Đang bật' : 'Đang tắt' }}</span>
        @if($upcoming)<a href="{{ route('consultations.index') }}" class="ml-auto text-sm font-semibold text-[var(--accent-ink)] hover:underline">{{ $upcoming }} lịch hẹn sắp tới →</a>@endif
    </div>
    <p class="text-sm text-[var(--muted)] mt-0.5">Khách chưa sẵn sàng đặt đơn có thể chọn giờ trống để bạn gọi tư vấn 15–60 phút. Lịch hiện ngay trên trang đặt đơn, trùng giờ thì tự ẩn.</p>

    <form method="POST" action="{{ route('settings.consult') }}" class="mt-5 space-y-5">
        @csrf @method('PUT')
        <input type="hidden" name="enabled" value="0">
        <label class="flex items-start gap-2.5" for="consult-enabled">
            <input id="consult-enabled" type="checkbox" name="enabled" value="1" x-model="on" class="mt-1 w-4 h-4 accent-[var(--accent)]">
            <span class="text-sm font-semibold">Cho khách đặt lịch gọi tư vấn trên trang đặt đơn</span>
        </label>

        <div class="grid sm:grid-cols-3 gap-4" :class="on ? '' : 'opacity-60'">
            <div>
                <label for="consult-duration" class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">Mỗi cuộc gọi</label>
                <select id="consult-duration" name="duration" class="w-full rounded-lg border border-[var(--line-strong)] bg-white py-2.5 px-3 text-sm">
                    @foreach(config('net.consult.durations') as $d)<option value="{{ $d }}" @selected((int) old('duration', $cp['duration']) === $d)>{{ $d }} phút</option>@endforeach
                </select>
            </div>
            <x-input name="notice_hours" id="consult-notice" type="number" min="0" max="168" label="Đặt trước ít nhất (giờ)" :value="old('notice_hours', $cp['notice_hours'])" />
            <x-input name="ahead_days" id="consult-ahead" type="number" min="1" max="60" label="Cho đặt xa nhất (ngày)" :value="old('ahead_days', $cp['ahead_days'])" />
        </div>

        <fieldset :class="on ? '' : 'opacity-60'">
            <legend class="block text-sm font-medium text-[var(--ink-2)] mb-2">Ngày nhận hẹn</legend>
            <div class="flex flex-wrap gap-2">
                @foreach(config('net.consult.weekdays') as $n => $label)
                    <label for="consult-day-{{ $n }}" class="inline-flex items-center gap-1.5 rounded-md border border-[var(--line-strong)] px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                        <input id="consult-day-{{ $n }}" type="checkbox" name="days[]" value="{{ $n }}" class="accent-[var(--accent)]" @checked(in_array($n, $days, true))>{{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset :class="on ? '' : 'opacity-60'">
            <legend class="block text-sm font-medium text-[var(--ink-2)] mb-2">Khung giờ (áp dụng cho các ngày đã chọn)</legend>
            <div class="space-y-2">
                @foreach($ranges as $i => $r)
                    <div class="flex items-center gap-2 text-sm">
                        <label class="sr-only" for="consult-r{{ $i }}-a">Từ</label>
                        <input id="consult-r{{ $i }}-a" type="time" name="ranges[{{ $i }}][0]" value="{{ $r[0] ?? '' }}" step="300" class="rounded-md border border-[var(--line-strong)] px-2 py-1.5 tnum">
                        <span class="text-[var(--muted)]">đến</span>
                        <label class="sr-only" for="consult-r{{ $i }}-b">Đến</label>
                        <input id="consult-r{{ $i }}-b" type="time" name="ranges[{{ $i }}][1]" value="{{ $r[1] ?? '' }}" step="300" class="rounded-md border border-[var(--line-strong)] px-2 py-1.5 tnum">
                        @if($i > 0)<span class="text-xs text-[var(--muted)]">(để trống nếu không dùng)</span>@endif
                    </div>
                @endforeach
            </div>
            @error('ranges')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>

        <fieldset :class="on ? '' : 'opacity-60'">
            <legend class="block text-sm font-medium text-[var(--ink-2)] mb-2">Cách gọi khách được chọn</legend>
            <div class="flex flex-wrap gap-2">
                @foreach(config('net.consult.channels') as $k => $ch)
                    <label for="consult-ch-{{ $k }}" class="inline-flex items-center gap-1.5 rounded-md border border-[var(--line-strong)] px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                        <input id="consult-ch-{{ $k }}" type="checkbox" name="channels[]" value="{{ $k }}" class="accent-[var(--accent)]" @checked(in_array($k, $channels, true))><i class="fa-solid {{ $ch['icon'] }} text-[var(--muted)]" aria-hidden="true"></i>{{ $ch['label'] }}
                    </label>
                @endforeach
            </div>
            @error('channels')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>

        <x-textarea name="note" id="consult-note" label="Lời nhắn cho khách (hiện ở lịch hẹn và email xác nhận)" rows="2" maxlength="500" :value="old('note', $cp['note'])" placeholder="VD: Mình sẽ gọi Zalo đúng giờ hẹn. Bạn chuẩn bị sẵn vài hình tham khảo nhé!" />

        <div class="flex flex-wrap items-center gap-3">
            <x-button icon="fa-check">Lưu</x-button>
            @if($cp['enabled'])
                <span class="text-sm text-[var(--muted)]">{{ collect($preview)->sum(fn ($d) => count($d['times'])) }} giờ trống trong {{ $cp['ahead_days'] }} ngày tới</span>
            @endif
        </div>
    </form>
</section>
