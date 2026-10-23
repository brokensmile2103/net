{{--
    Đặt lịch gọi tư vấn trên trang đặt đơn (chỉ hiện khi studio bật). Ô liên hệ có tiền tố c_ để không lẫn với form đặt đơn.
    Lỗi nằm trong túi lỗi "consult". Biến: $studio, $consultSlots
--}}
@php
    $cp = $studio->consultPrefs();
    $ce = $errors->consult;
    $firstDate = old('date', $consultSlots[0]['date'] ?? '');
@endphp
<section id="tu-van" class="mt-12 scroll-mt-6" aria-labelledby="h-consult">
    <h2 id="h-consult" class="pf-section-title">Đặt lịch gọi tư vấn</h2>
    <div class="pf-card rounded-lg border border-[var(--line)] bg-white p-5 md:p-6">
        <p class="text-sm text-[var(--ink-2)]">Chưa chắc mình cần gì, hay muốn nghe tư vấn trước khi đặt? Chọn giờ trống, {{ $studio->name }} sẽ gọi cho bạn khoảng {{ $cp['duration'] }} phút — miễn phí.</p>
        @if($cp['note'])<p class="text-sm text-[var(--muted)] mt-1.5 whitespace-pre-line">{{ $cp['note'] }}</p>@endif

        @if(! $consultSlots)
            <p class="mt-4 rounded-lg bg-stone-50 px-4 py-3 text-sm text-[var(--muted)]">Lịch {{ $cp['ahead_days'] }} ngày tới đã kín. Bạn liên hệ trực tiếp ở cuối trang nhé.</p>
        @else
            <form method="POST" action="{{ route('public.consult.store', $studio->slug) }}" class="mt-5 space-y-5"
                  x-data="{ slots: @js($consultSlots), date: @js($firstDate), time: @js(old('time', '')), channel: @js(old('channel', $cp['channels'][0])),
                            get times() { return (this.slots.find((d) => d.date === this.date) || { times: [] }).times } }">
                @csrf
                <x-form-guard :name="'consult-'.$studio->id" />
                @if($ce->any())
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3" role="alert">
                        <ul class="list-disc pl-4 space-y-0.5">@foreach($ce->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
                    </div>
                @endif

                <fieldset class="min-w-0">
                    <legend class="block text-sm font-medium text-[var(--ink-2)] mb-2">Chọn ngày</legend>
                    <div class="flex gap-2 overflow-x-auto pb-1 consult-scroll" role="radiogroup">
                        <template x-for="d in slots" :key="d.date">
                            <label :for="'cd-' + d.date" class="pf-choice shrink-0 w-[4.6rem] text-center rounded-lg border px-2 py-2 cursor-pointer transition"
                                   :class="date === d.date ? 'border-[var(--accent)] bg-[var(--accent-soft)]' : 'border-[var(--line-strong)] hover:border-[var(--ink)]'">
                                <input type="radio" name="date" :id="'cd-' + d.date" :value="d.date" x-model="date" @change="time = ''" class="sr-only">
                                <span class="block text-xs text-[var(--muted)]" x-text="d.weekday"></span>
                                <span class="block text-sm font-semibold" x-text="d.label"></span>
                            </label>
                        </template>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="block text-sm font-medium text-[var(--ink-2)] mb-2">Chọn giờ <span class="font-normal text-[var(--muted)]">({{ $cp['duration'] }} phút)</span></legend>
                    <div class="flex flex-wrap gap-2" role="radiogroup">
                        <template x-for="t in times" :key="date + t">
                            <label :for="'ct-' + t.replace(':', '')" class="pf-choice rounded-md border px-3 py-1.5 text-sm font-semibold tnum cursor-pointer transition"
                                   :class="time === t ? 'border-[var(--accent)] bg-[var(--accent)] text-[var(--on-accent)]' : 'border-[var(--line-strong)] hover:border-[var(--ink)]'">
                                <input type="radio" name="time" :id="'ct-' + t.replace(':', '')" :value="t" x-model="time" class="sr-only" required>
                                <span x-text="t"></span>
                            </label>
                        </template>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="block text-sm font-medium text-[var(--ink-2)] mb-2">Gọi qua</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($cp['channels'] as $k)
                            <label for="cch-{{ $k }}" class="pf-choice inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm cursor-pointer"
                                   :class="channel === @js($k) ? 'border-[var(--accent)] bg-[var(--accent-soft)]' : 'border-[var(--line-strong)]'">
                                <input id="cch-{{ $k }}" type="radio" name="channel" value="{{ $k }}" x-model="channel" class="accent-[var(--accent)]">
                                <i class="fa-solid {{ config('net.consult.channels.'.$k.'.icon') }} text-[var(--muted)]" aria-hidden="true"></i>{{ config('net.consult.channels.'.$k.'.label') }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2"><x-input name="c_name" id="c-name" label="Tên của bạn *" :value="old('c_name')" required maxlength="100" autocomplete="name" /></div>
                    <x-input name="c_phone" id="c-phone" label="Số điện thoại / Zalo" :value="old('c_phone')" inputmode="tel" autocomplete="tel" maxlength="20" ::required="channel !== 'meet'" />
                    <x-input name="c_email" id="c-email" type="email" label="Email (nhận xác nhận lịch hẹn)" :value="old('c_email')" autocomplete="email" maxlength="255" />
                    <div class="sm:col-span-2"><x-textarea name="c_note" id="c-note" label="Bạn muốn trao đổi về gì?" rows="2" maxlength="1000" :value="old('c_note')" placeholder="VD: Mình mở tiệm bánh, cần logo và bao bì, chưa rõ ngân sách bao nhiêu là hợp lý…" /></div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-button size="lg" icon="fa-calendar-check" class="pf-submit" ::disabled="!time">Đặt lịch gọi</x-button>
                    <p class="text-sm text-[var(--muted)]" x-show="time" x-cloak x-text="'Hẹn lúc ' + time + ', ' + ((slots.find((d) => d.date === date) || {}).weekday || '') + ' ' + ((slots.find((d) => d.date === date) || {}).label || '')"></p>
                </div>
            </form>
        @endif
    </div>
</section>
