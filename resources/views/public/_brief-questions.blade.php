{{--
    Câu hỏi mẫu brief của từng dịch vụ (trang đặt đơn). Chỉ hiện khối của dịch vụ đang chọn; ô bắt buộc chỉ "required"
    khi dịch vụ đó được chọn (máy chủ kiểm tra lại). Tên ô: answers[{service_id}][{câu}] (chọn nhiều: thêm []).
    Biến: $services
--}}
@foreach($services as $s)
    @php $questions = $s->briefQuestions(); @endphp
    @continue(! $questions)
    <div x-show="service === '{{ $s->id }}'" x-cloak class="pf-brief space-y-4 rounded-lg border border-[var(--line)] p-4 md:p-5" role="group" aria-labelledby="brief-h-{{ $s->id }}">
        <div>
            <p id="brief-h-{{ $s->id }}" class="font-semibold"><i class="fa-regular fa-rectangle-list text-[var(--accent)] mr-1.5" aria-hidden="true"></i>Vài câu hỏi cho {{ mb_strtolower($s->name) }}</p>
            <p class="text-xs text-[var(--muted)] mt-0.5">Trả lời kỹ một chút là đỡ phải nhắn qua lại. Câu có dấu * là bắt buộc.</p>
        </div>
        @foreach($questions as $i => $q)
            @php
                $field = 'answers['.$s->id.']['.$i.']';
                $id = 'ans-'.$s->id.'-'.$i;
                $old = old('answers.'.$s->id.'.'.$i);
                $label = $q['label'].($q['required'] ? ' *' : '');
            @endphp
            <div>
                @if(in_array($q['type'], ['choice', 'multi'], true))
                    <fieldset>
                        <legend class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">{{ $label }}</legend>
                        @if($q['hint'])<p class="text-xs text-[var(--muted)] -mt-1 mb-1.5">{{ $q['hint'] }}</p>@endif
                        <div class="flex flex-wrap gap-2">
                            @foreach($q['options'] as $k => $opt)
                                @php $checked = $q['type'] === 'multi' ? in_array($opt, (array) $old, true) : $old === $opt; @endphp
                                <label for="{{ $id }}-{{ $k }}" class="pf-choice inline-flex items-center gap-2 rounded-full border border-[var(--line-strong)] px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                    <input id="{{ $id }}-{{ $k }}" type="{{ $q['type'] === 'multi' ? 'checkbox' : 'radio' }}" name="{{ $field }}{{ $q['type'] === 'multi' ? '[]' : '' }}" value="{{ $opt }}" @checked($checked)
                                           class="accent-[var(--accent)]" @if($q['required'] && $q['type'] === 'choice') :required="service === '{{ $s->id }}'" @endif>{{ $opt }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @else
                    <label for="{{ $id }}" class="block text-sm font-medium text-[var(--ink-2)] mb-1.5">{{ $label }}</label>
                    @if($q['type'] === 'long')
                        <textarea id="{{ $id }}" name="{{ $field }}" rows="3" maxlength="3000" @if($q['required']) :required="service === '{{ $s->id }}'" @endif
                                  class="w-full rounded-lg border border-[var(--line-strong)] bg-white py-2.5 px-3.5 text-[0.95rem] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)]">{{ is_string($old) ? $old : '' }}</textarea>
                    @else
                        <input id="{{ $id }}" name="{{ $field }}" value="{{ is_string($old) ? $old : '' }}" maxlength="500" @if($q['required']) :required="service === '{{ $s->id }}'" @endif
                               class="w-full rounded-lg border border-[var(--line-strong)] bg-white py-2.5 px-3.5 text-[0.95rem] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/25 focus:border-[var(--accent)]">
                    @endif
                    @if($q['hint'])<p class="text-xs text-[var(--muted)] mt-1">{{ $q['hint'] }}</p>@endif
                @endif
                @error('answers.'.$s->id.'.'.$i)<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>
@endforeach
