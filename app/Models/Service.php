<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 1 dịch vụ trong bảng giá (VD: Thiết kế logo, Bộ nhận diện, Banner quảng cáo). */
class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'price', 'price_is_from', 'duration_days', 'revision_rounds',
        'deposit_percent', 'brief_hint', 'brief_questions', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_is_from' => 'boolean',
            'is_public' => 'boolean',
            'duration_days' => 'integer',
            'revision_rounds' => 'integer',
            'deposit_percent' => 'integer',
            'brief_questions' => 'array',
        ];
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Câu hỏi mẫu brief của dịch vụ, đã chuẩn hoá.
     *
     * @return list<array{label: string, type: string, options: list<string>, required: bool, hint: string}>
     */
    public function briefQuestions(): array
    {
        return self::normalizeQuestions(is_array($this->brief_questions) ? $this->brief_questions : []);
    }

    /**
     * Làm sạch danh sách câu hỏi (từ form Dịch vụ hoặc từ DB): bỏ câu trống, kiểu lạ → "Trả lời ngắn",
     * câu chọn mà không có lựa chọn → "Trả lời ngắn", giới hạn số câu / số lựa chọn / độ dài.
     */
    public static function normalizeQuestions(array $raw): array
    {
        $types = array_keys(config('net.brief.types'));
        $clean = fn ($v, $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v))), 0, $max);
        $out = [];
        foreach ($raw as $q) {
            if (! is_array($q) || ($label = $clean($q['label'] ?? '', 160)) === '') {
                continue;
            }
            $type = in_array($q['type'] ?? '', $types, true) ? $q['type'] : 'short';
            $options = is_array($q['options'] ?? null) ? $q['options'] : preg_split('/\r?\n/', (string) ($q['options'] ?? ''));
            $options = array_values(array_unique(array_filter(array_map(fn ($o) => $clean($o, 80), $options), fn ($o) => $o !== '')));
            $options = array_slice($options, 0, (int) config('net.brief.max_options', 12));
            if (in_array($type, ['choice', 'multi'], true) && count($options) < 2) {
                $type = 'short';
            }
            $out[] = [
                'label' => $label,
                'type' => $type,
                'options' => in_array($type, ['choice', 'multi'], true) ? $options : [],
                'required' => (bool) ($q['required'] ?? false),
                'hint' => $clean($q['hint'] ?? '', 200),
            ];
            if (count($out) >= (int) config('net.brief.max_questions', 12)) {
                break;
            }
        }

        return $out;
    }

    /**
     * Kiểm tra + gom câu trả lời mẫu brief khách gửi. Câu chọn chỉ nhận đúng lựa chọn đã đặt (không nhận giá trị bịa).
     * Lưu kèm nội dung câu hỏi để sau này sửa mẫu brief cũng không làm lệch đơn cũ.
     *
     * @return array{answers: list<array{question: string, type: string, answer: string|list<string>}>, errors: array<string, string>}
     */
    public function collectAnswers(array $input): array
    {
        $answers = [];
        $errors = [];
        foreach ($this->briefQuestions() as $i => $q) {
            $raw = $input[$i] ?? null;
            if ($q['type'] === 'multi') {
                $value = array_values(array_intersect($q['options'], array_map('strval', (array) $raw)));
                $empty = $value === [];
            } else {
                $value = mb_substr(trim((string) (is_array($raw) ? '' : $raw)), 0, $q['type'] === 'long' ? 3000 : 500);
                if ($q['type'] === 'choice' && ! in_array($value, $q['options'], true)) {
                    $value = '';
                }
                $empty = $value === '';
            }
            if ($empty) {
                if ($q['required']) {
                    $errors['answers.'.$this->id.'.'.$i] = 'Hãy trả lời: '.$q['label'];
                }

                continue;
            }
            $answers[] = ['question' => $q['label'], 'type' => $q['type'], 'answer' => $value];
        }

        return ['answers' => $answers, 'errors' => $errors];
    }

    /** "1.500.000đ", "từ 1.500.000đ" hoặc "Báo giá sau". */
    public function priceLabel(): string
    {
        if ($this->price === null) {
            return 'Báo giá sau';
        }

        return ($this->price_is_from ? 'từ ' : '').money($this->price).'đ';
    }

    public function effectiveRevisionRounds(Studio $studio): int
    {
        return (int) ($this->revision_rounds ?? $studio->default_revision_rounds);
    }

    public function effectiveDepositPercent(Studio $studio): int
    {
        return (int) ($this->deposit_percent ?? $studio->default_deposit_percent);
    }
}
