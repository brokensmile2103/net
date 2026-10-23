<?php

namespace App\Services\Ai;

use App\Models\Studio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gọi Google Gemini bằng API key riêng của từng studio, tự chuyển model khi cần.
 *
 * Chuỗi model: studio tự chọn ở Cài đặt, chưa chọn thì theo config('net.ai.default_models') — mặc định 3.5 Flash (thông minh, ~20 lượt/ngày miễn phí)
 * → 3.5 Flash Lite → 3.1 Flash Lite (~500 lượt/ngày mỗi cái). Chuyển sang model sau khi:
 *  - 429 hết hạn mức NGÀY → đánh dấu model đó "hết lượt" tới 0h giờ Thái Bình Dương (Google reset lúc đó);
 *  - 429 hết hạn mức PHÚT → tạm bỏ qua model đó ~1 phút;
 *  - 404 (model bị khai tử / key mới không dùng được) → bỏ qua 1 ngày;
 *  - 5xx / quá tải / mất kết nối → thử model sau ngay.
 * Key sai / bị chặn (400 API_KEY_INVALID, 403) thì dừng luôn — đổi model cũng vô ích.
 *
 * Đếm số lượt dùng mỗi model mỗi ngày (cache) để hiện ở Cài đặt. Cache gắn với dấu vân tay của key,
 * đổi key là đếm lại từ đầu.
 */
class Gemini
{
    /**
     * Sinh nội dung. Có $schema → trả mảng đã giải mã JSON; không có → trả chuỗi văn bản.
     *
     * @return array{result: array|string, model: string, label: string}
     */
    public function generate(Studio $studio, string $system, string $prompt, ?array $schema = null): array
    {
        $key = $studio->geminiKey();
        if (! $key) {
            throw new AiException('Chưa có API key Gemini. Vào Cài đặt → Trợ lý AI để dán key miễn phí của bạn.', AiException::NO_KEY);
        }

        $started = microtime(true);
        $budget = (int) config('net.ai.total_budget', 75);
        $tried = 0;
        $quotaHit = false;   // có model hết lượt NGÀY
        $minuteHit = false;  // có model chỉ tạm hết lượt PHÚT
        $lastError = null;

        foreach ($this->models($studio) as $model) {
            if ($this->isBlocked($key, $model['id'])) {
                $quotaHit = $quotaHit || $this->isExhausted($key, $model['id']);
                $minuteHit = $minuteHit || Cache::has($this->cacheKey('cooldown', $key, $model['id']));

                continue;
            }
            if ($tried > 0 && microtime(true) - $started > $budget - 10) {
                break; // không đủ thời gian thử thêm, tránh vượt giới hạn chạy PHP của hosting
            }
            $tried++;

            try {
                $response = Http::withHeaders(['x-goog-api-key' => $key])
                    ->acceptJson()
                    ->timeout((int) config('net.ai.timeout', 40))
                    ->post(rtrim(config('net.ai.endpoint'), '/').'/models/'.rawurlencode($model['id']).':generateContent', $this->body($system, $prompt, $schema));
            } catch (ConnectionException $e) {
                Log::warning('Gemini: không kết nối được', ['model' => $model['id'], 'error' => $e->getMessage()]);
                $lastError = 'Không kết nối được tới Google Gemini.';

                continue;
            }

            if ($response->successful()) {
                $text = $this->extractText($response);
                if ($text === null) {
                    $lastError = 'Gemini không trả lời (có thể nội dung bị bộ lọc an toàn chặn).';

                    continue;
                }

                $result = $text;
                if ($schema !== null) {
                    $result = $this->decodeJson($text);
                    if ($result === null) {
                        Log::warning('Gemini: JSON không hợp lệ', ['model' => $model['id'], 'text' => mb_substr($text, 0, 500)]);
                        $lastError = 'Gemini trả về dữ liệu không đúng định dạng.';

                        continue;
                    }
                }

                $this->countUse($key, $model['id']);

                return ['result' => $result, 'model' => $model['id'], 'label' => $model['label']];
            }

            $status = $response->status();
            $error = (array) $response->json('error', []);
            $apiStatus = (string) ($error['status'] ?? '');
            $message = (string) ($error['message'] ?? '');

            if ($this->isKeyProblem($status, $apiStatus, $message, $error)) {
                throw new AiException('API key Gemini không hợp lệ hoặc đã bị thu hồi. Vào Cài đặt → Trợ lý AI để dán key mới.', AiException::KEY_INVALID);
            }

            if ($status === 429) {
                if ($this->markQuota($key, $model['id'], $error)) {
                    $quotaHit = true;
                } else {
                    $minuteHit = true;
                }
                $lastError = null;

                continue;
            }

            if ($status === 404) {
                Cache::put($this->cacheKey('gone', $key, $model['id']), true, now()->addDay());
            }

            Log::warning('Gemini lỗi', ['model' => $model['id'], 'http' => $status, 'status' => $apiStatus, 'message' => mb_substr($message, 0, 300)]);
            $lastError = $status >= 500 ? 'Máy chủ Gemini đang quá tải.' : 'Gemini từ chối yêu cầu ('.$status.').';
        }

        if ($minuteHit && $lastError === null) {
            throw new AiException('Gọi AI hơi dồn dập nên Google tạm chặn — đợi khoảng 1 phút rồi thử lại nhé.', AiException::QUOTA);
        }

        if ($quotaHit && $lastError === null) {
            throw new AiException('Hôm nay đã dùng hết lượt AI miễn phí của các model. Google reset lượt lúc '.$this->resetAt()->timezone(config('app.timezone'))->format('H:i').' — hoặc bật thanh toán cho key để dùng không giới hạn.', AiException::QUOTA);
        }

        throw new AiException(($lastError ?? 'Trợ lý AI đang bận.').' Vui lòng thử lại sau ít phút.', AiException::UNAVAILABLE);
    }

    /**
     * Kiểm tra key khi lưu: gọi danh sách mô hình (không tốn lượt sinh nội dung). true = dùng được.
     * Nhân tiện lưu lại danh sách mô hình key này dùng được để gợi ý ở phần chọn mô hình.
     */
    public function verifyKey(string $key): bool
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->timeout(15)
                ->get(rtrim(config('net.ai.endpoint'), '/').'/models', ['pageSize' => 1000]);
        } catch (ConnectionException) {
            throw new AiException('Không kết nối được tới Google để kiểm tra key. Vui lòng thử lại.', AiException::UNAVAILABLE);
        }

        if ($response->successful()) {
            $this->rememberModels($key, $response);

            return true;
        }

        $error = (array) $response->json('error', []);
        if ($this->isKeyProblem($response->status(), (string) ($error['status'] ?? ''), (string) ($error['message'] ?? ''), $error)) {
            return false;
        }

        throw new AiException('Google đang trả lỗi ('.$response->status().') nên chưa kiểm tra được key. Vui lòng thử lại sau.', AiException::UNAVAILABLE);
    }

    /**
     * Mô hình Gemini (dùng được generateContent) mà key của studio gọi được — lấy từ Google, nhớ 7 ngày.
     * null = chưa biết (chưa có key / Google lỗi); khi đó chỉ gợi ý danh sách mặc định.
     *
     * @return list<string>|null
     */
    public function availableModels(Studio $studio, bool $refresh = false): ?array
    {
        $key = $studio->geminiKey();
        if (! $key) {
            return null;
        }

        $cacheKey = $this->cacheKey('models', $key, 'all');
        if (! $refresh && is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->timeout(10)
                ->get(rtrim(config('net.ai.endpoint'), '/').'/models', ['pageSize' => 1000]);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $this->rememberModels($key, $response) : null;
    }

    /** Như availableModels() nhưng chỉ đọc bộ nhớ đệm — dùng khi vẽ trang, không bắt người dùng chờ Google. */
    public function cachedModels(Studio $studio): ?array
    {
        $key = $studio->geminiKey();
        $cached = $key ? Cache::get($this->cacheKey('models', $key, 'all')) : null;

        return is_array($cached) ? $cached : null;
    }

    /**
     * Tên + số lượt miễn phí ước tính cho 1 mô hình (kể cả mô hình nhập tay).
     *
     * @return array{id: string, label: string, daily: int}
     */
    public static function describe(string $id): array
    {
        $label = preg_replace_callback('/\b([a-z])/', fn ($m) => strtoupper($m[1]), str_replace('-', ' ', $id));
        $label = str_replace(' Preview', ' (preview)', $label);

        // Đo tháng 9/2026 (Google không công bố): bản Flash ~20 lượt/ngày, bản Lite ~500 lượt/ngày. Chỉ để hiển thị.
        return ['id' => $id, 'label' => $label, 'daily' => str_contains($id, 'lite') ? 500 : 20];
    }

    /** Chuẩn hoá tên mô hình người dùng nhập: bỏ "models/", khoảng trắng, chữ hoa. Không hợp lệ → null. */
    public static function normalizeModelId(?string $id): ?string
    {
        $id = strtolower(trim((string) $id));
        $id = preg_replace('#^models/#', '', $id);

        return preg_match('/^[a-z0-9][a-z0-9.\-]{2,63}$/', $id) ? $id : null;
    }

    /**
     * Tình trạng từng model hôm nay cho trang Cài đặt.
     *
     * @return list<array{id: string, label: string, daily: int, used: int, exhausted: bool, gone: bool}>
     */
    public function usage(Studio $studio): array
    {
        $key = $studio->geminiKey();

        return array_map(fn ($m) => $m + [
            'used' => $key ? (int) Cache::get($this->cacheKey('used:'.$this->quotaDay(), $key, $m['id']), 0) : 0,
            'exhausted' => $key ? $this->isExhausted($key, $m['id']) : false,
            'gone' => $key ? (bool) Cache::get($this->cacheKey('gone', $key, $m['id'])) : false,
        ], $this->models($studio));
    }

    /** Thời điểm Google reset hạn mức ngày (0h giờ Thái Bình Dương kế tiếp). */
    public function resetAt(): Carbon
    {
        return Carbon::now(config('net.ai.reset_timezone', 'America/Los_Angeles'))->addDay()->startOfDay();
    }

    // ------------------------------------------------------------------

    /** @return list<array{id: string, label: string, daily: int}> */
    private function models(Studio $studio): array
    {
        return array_map(fn ($id) => self::describe($id), $studio->aiModelIds());
    }

    /** @return list<string> */
    private function rememberModels(string $key, Response $response): array
    {
        $ids = collect((array) $response->json('models', []))
            ->filter(fn ($m) => is_array($m) && in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true))
            ->map(fn ($m) => self::normalizeModelId((string) ($m['name'] ?? '')))
            // Chỉ gợi ý mô hình viết chữ — bỏ bản tạo ảnh, giọng nói, live, nhúng…
            ->filter(fn ($id) => $id !== null && str_starts_with($id, 'gemini') && ! preg_match('/tts|image|embedding|live|audio|robotics|transcribe|banana/', $id))
            ->unique()->sort()->values()->all();

        Cache::put($this->cacheKey('models', $key, 'all'), $ids, now()->addDays(7));

        return $ids;
    }

    private function body(string $system, string $prompt, ?array $schema): array
    {
        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        ];

        if ($schema !== null) {
            $body['generationConfig'] = ['responseMimeType' => 'application/json', 'responseSchema' => $schema];
        }

        return $body;
    }

    private function extractText(Response $response): ?string
    {
        $parts = (array) $response->json('candidates.0.content.parts', []);
        $text = '';
        foreach ($parts as $part) {
            if (is_array($part) && empty($part['thought']) && isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function decodeJson(string $text): ?array
    {
        // Phòng khi model vẫn bọc ```json … ``` dù đã yêu cầu JSON thuần.
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }

    private function isKeyProblem(int $status, string $apiStatus, string $message, array $error): bool
    {
        if ($status === 401 || $status === 403 || $apiStatus === 'PERMISSION_DENIED' || $apiStatus === 'UNAUTHENTICATED') {
            return true;
        }

        $reasons = collect($error['details'] ?? [])->pluck('reason')->filter()->all();

        return $status === 400 && (in_array('API_KEY_INVALID', $reasons, true) || stripos($message, 'API key not valid') !== false || stripos($message, 'API key expired') !== false);
    }

    /** 429: hết lượt NGÀY → khoá tới giờ reset (trả true); hết lượt PHÚT → khoá theo retryDelay, mặc định 60 giây (trả false). */
    private function markQuota(string $key, string $model, array $error): bool
    {
        $details = collect($error['details'] ?? []);
        $quotaIds = $details->flatMap(fn ($d) => collect($d['violations'] ?? [])->pluck('quotaId'))->filter()->implode(' ');
        $perDay = stripos($quotaIds, 'PerDay') !== false || stripos((string) ($error['message'] ?? ''), 'per day') !== false;

        if ($perDay) {
            Cache::put($this->cacheKey('exhausted:'.$this->quotaDay(), $key, $model), true, $this->resetAt());

            return true;
        }

        $delay = (string) ($details->firstWhere('retryDelay')['retryDelay'] ?? '60s');
        $seconds = max(10, min(300, (int) ceil((float) rtrim($delay, 's'))));
        Cache::put($this->cacheKey('cooldown', $key, $model), true, now()->addSeconds($seconds));

        return false;
    }

    private function isBlocked(string $key, string $model): bool
    {
        return $this->isExhausted($key, $model)
            || Cache::has($this->cacheKey('cooldown', $key, $model))
            || Cache::has($this->cacheKey('gone', $key, $model));
    }

    private function isExhausted(string $key, string $model): bool
    {
        return Cache::has($this->cacheKey('exhausted:'.$this->quotaDay(), $key, $model));
    }

    private function countUse(string $key, string $model): void
    {
        $cacheKey = $this->cacheKey('used:'.$this->quotaDay(), $key, $model);
        Cache::add($cacheKey, 0, now()->addDays(2));
        Cache::increment($cacheKey);
    }

    /** Ngày tính hạn mức theo giờ Thái Bình Dương (khớp lúc Google reset). */
    private function quotaDay(): string
    {
        return Carbon::now(config('net.ai.reset_timezone', 'America/Los_Angeles'))->format('Ymd');
    }

    /** Không lưu key gốc trong tên cache — chỉ dấu vân tay. */
    private function cacheKey(string $kind, string $apiKey, string $model): string
    {
        return 'net:ai:'.$kind.':'.substr(hash('sha256', $apiKey), 0, 16).':'.$model;
    }
}
