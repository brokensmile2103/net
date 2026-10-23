{{--
    Trình soạn "Mẫu brief" của 1 dịch vụ: các câu hỏi khách trả lời khi chọn dịch vụ này trên trang đặt đơn.
    Gửi lên dạng JSON trong ô ẩn brief_questions (ServiceController làm sạch lại). JS: briefBuilder trong resources/js/app.js.
    Biến: $s (Service|null), $p (tiền tố id), $mine (form này vừa gửi lỗi?)
--}}
@php
    $initial = $mine && old('brief_questions') !== null
        ? \App\Models\Service::normalizeQuestions((array) json_decode((string) old('brief_questions'), true))
        : ($s?->briefQuestions() ?? []);
    $presets = collect(config('net.brief.presets'))->map(fn ($p) => ['label' => $p['label'], 'questions' => \App\Models\Service::normalizeQuestions($p['questions'])]);
@endphp
<div class="sm:col-span-2 rounded-lg border border-[var(--line)] bg-stone-50/60 p-4"
     x-data="briefBuilder(@js($initial), @js($presets), @js(config('net.brief.types')), {{ (int) config('net.brief.max_questions') }})">
    <input type="hidden" name="brief_questions" :value="json">
    <div class="flex flex-wrap items-center gap-2">
        <p class="text-sm font-semibold flex-1 min-w-40">Mẫu brief <span class="font-normal text-[var(--muted)]" x-text="questions.length ? '· ' + questions.length + ' câu hỏi' : '· chưa có'"></span></p>
        <label class="sr-only" for="{{ $p }}preset">Dùng mẫu có sẵn</label>
        <select id="{{ $p }}preset" class="rounded-md border border-[var(--line-strong)] bg-white py-1.5 pl-2 pr-7 text-sm" @change="usePreset($el.value); $el.value = ''">
            <option value="">Dùng mẫu có sẵn…</option>
            @foreach($presets as $key => $preset)<option value="{{ $key }}">{{ $preset['label'] }}</option>@endforeach
        </select>
    </div>
    <p class="text-xs text-[var(--muted)] mt-1">Khách chọn dịch vụ này trên trang đặt đơn sẽ trả lời các câu dưới đây — hỏi đúng thứ bạn cần, bớt phải nhắn qua lại.</p>

    <ol class="mt-3 space-y-3">
        <template x-for="(q, i) in questions" :key="q.key">
            <li class="rounded-md bg-white ring-1 ring-[var(--line)] p-3">
                <div class="flex items-start gap-2">
                    <span class="mt-2 text-xs font-semibold text-[var(--muted)] tnum w-5" x-text="(i + 1) + '.'"></span>
                    <div class="flex-1 min-w-0 grid sm:grid-cols-[1fr_9rem] gap-2">
                        <label class="sr-only" :for="'{{ $p }}q' + q.key + 'label'">Câu hỏi</label>
                        <input :id="'{{ $p }}q' + q.key + 'label'" x-model="q.label" maxlength="160" placeholder="Câu hỏi cho khách" class="rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm">
                        <label class="sr-only" :for="'{{ $p }}q' + q.key + 'type'">Kiểu trả lời</label>
                        <select :id="'{{ $p }}q' + q.key + 'type'" x-model="q.type" class="rounded-md border border-[var(--line-strong)] py-1.5 pl-2 pr-7 text-sm">
                            <template x-for="(label, t) in types" :key="t"><option :value="t" x-text="label" :selected="q.type === t"></option></template>
                        </select>
                        <template x-if="q.type === 'choice' || q.type === 'multi'">
                            <div class="sm:col-span-2">
                                <label class="text-xs text-[var(--muted)]" :for="'{{ $p }}q' + q.key + 'opts'">Các lựa chọn — mỗi dòng 1 lựa chọn</label>
                                <textarea :id="'{{ $p }}q' + q.key + 'opts'" x-model="q.optionsText" rows="3" class="mt-1 w-full rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-sm"></textarea>
                            </div>
                        </template>
                        <label class="sr-only" :for="'{{ $p }}q' + q.key + 'hint'">Gợi ý cho khách</label>
                        <input :id="'{{ $p }}q' + q.key + 'hint'" x-model="q.hint" maxlength="200" placeholder="Gợi ý nhỏ dưới câu hỏi (không bắt buộc)" class="sm:col-span-2 rounded-md border border-[var(--line-strong)] px-2.5 py-1.5 text-xs">
                        <label class="flex items-center gap-2 text-xs sm:col-span-2" :for="'{{ $p }}q' + q.key + 'req'">
                            <input type="checkbox" :id="'{{ $p }}q' + q.key + 'req'" x-model="q.required" class="accent-[var(--accent)]"> Bắt buộc trả lời
                        </label>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button type="button" class="w-7 h-7 rounded hover:bg-stone-100 text-[var(--muted)] text-xs" @click="move(i, -1)" x-show="i > 0" :aria-label="'Đưa câu ' + (i + 1) + ' lên'"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                        <button type="button" class="w-7 h-7 rounded hover:bg-red-50 text-[var(--muted)] hover:text-red-600 text-xs" @click="questions.splice(i, 1)" :aria-label="'Xoá câu ' + (i + 1)"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                </div>
            </li>
        </template>
    </ol>
    <button type="button" class="mt-3 text-sm font-semibold text-[var(--accent-ink)]" @click="add()" x-show="questions.length < max"><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Thêm câu hỏi</button>
    @error('brief_questions')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
