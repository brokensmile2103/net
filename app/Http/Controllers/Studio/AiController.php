<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Services\Ai\AiException;
use App\Services\Ai\Assistant;
use App\Services\Ai\Gemini;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * Trợ lý AI (Gemini, key riêng của từng studio): cài key, lên đơn bằng ngôn ngữ tự nhiên, trợ giúp trong trang đơn.
 * Kết quả luôn là ĐỀ XUẤT (form điền sẵn / chữ để sao chép) — freelancer xem lại rồi mới lưu hay gửi.
 */
class AiController extends Controller
{
    use ResolvesStudio;

    // ------------------------------------------------------------------ Cài đặt

    public function saveKey(Request $request, Gemini $gemini): RedirectResponse
    {
        $studio = $this->studio($request);
        $request->merge(['gemini_api_key' => preg_replace('/\s+/', '', (string) $request->input('gemini_api_key'))]);
        // Chỉ chặn thứ chắc chắn sai (quá ngắn, có dấu tiếng Việt / ký tự điều khiển). Định dạng key Google có thể đổi
        // (VD key mới có dấu chấm) — đúng hay sai do bước hỏi thẳng Google bên dưới quyết định.
        $data = $request->validate(
            ['gemini_api_key' => ['required', 'string', 'min:20', 'max:300', 'regex:/^[\x21-\x7E]+$/']],
            ['gemini_api_key.required' => 'Hãy dán API key Gemini.', 'gemini_api_key.min' => 'API key quá ngắn — hãy sao chép đủ cả chuỗi.', 'gemini_api_key.regex' => 'API key có ký tự lạ (dấu tiếng Việt?) — hãy sao chép lại từ Google AI Studio.'],
        );

        try {
            if (! $gemini->verifyKey($data['gemini_api_key'])) {
                return back()->withErrors(['gemini_api_key' => 'Google báo key này không hợp lệ. Kiểm tra lại đã sao chép đúng và key chưa bị xoá.'])->withFragment('tro-ly-ai');
            }
        } catch (AiException $e) {
            return back()->withErrors(['gemini_api_key' => $e->getMessage()])->withFragment('tro-ly-ai');
        }

        $this->storeKey($studio, $data['gemini_api_key']);

        return redirect()->to(route('settings.index').'#tro-ly-ai')->with('status', 'Đã bật Trợ lý AI. Thử ngay: Đơn → Đơn mới → "Lên đơn nhanh bằng AI".');
    }

    /** Chọn mô hình (thứ tự thử). "Dùng mặc định" = xoá lựa chọn riêng, theo NET_AI_MODELS của hệ thống. */
    public function saveModels(Request $request, Gemini $gemini): RedirectResponse
    {
        $studio = $this->studio($request);
        $back = redirect()->to(route('settings.index').'#tro-ly-ai');

        if ($request->boolean('reset')) {
            Studio::query()->whereKey($studio->id)->update(['ai_models' => null]);

            return $back->with('status', 'Đã chuyển về mô hình mặc định.');
        }

        $max = (int) config('net.ai.max_models', 3);
        $request->validate(['models' => 'required|array|max:'.$max, 'models.*' => 'nullable|string|max:80']);

        $ids = [];
        $errors = [];
        foreach (array_values((array) $request->input('models')) as $i => $raw) {
            if (blank($raw)) {
                continue;
            }
            $id = Gemini::normalizeModelId($raw);
            if ($id === null) {
                $errors['models.'.$i] = 'Tên mô hình "'.$raw.'" không hợp lệ — VD: gemini-3.5-flash.';
            } elseif (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        if ($ids === [] && $errors === []) {
            $errors['models.0'] = 'Chọn ít nhất 1 mô hình (hoặc bấm "Dùng mặc định").';
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors)->withFragment('tro-ly-ai');
        }

        Studio::query()->whereKey($studio->id)->update(['ai_models' => json_encode($ids)]);
        $back->with('status', 'Đã lưu mô hình AI: '.implode(' → ', $ids).'.');

        // Không chặn (danh sách của Google có thể chưa cập nhật), chỉ nhắc khi key này không thấy mô hình đó.
        $available = $gemini->availableModels($studio->fresh(), refresh: true);
        if ($available) {
            $unknown = array_values(array_diff($ids, $available));
            if ($unknown) {
                $back->with('warning', 'Key của bạn hiện không thấy mô hình: '.implode(', ', $unknown).'. Nếu gọi lỗi, Nét sẽ tự bỏ qua và dùng mô hình kế tiếp.');
            }
        }

        return $back;
    }

    public function removeKey(Request $request): RedirectResponse
    {
        $this->storeKey($this->studio($request), null);

        return redirect()->to(route('settings.index').'#tro-ly-ai')->with('status', 'Đã gỡ API key — Trợ lý AI đã tắt.');
    }

    // ------------------------------------------------------------------ Lên đơn

    public function draftOrder(Request $request, Assistant $assistant): RedirectResponse
    {
        $studio = $this->studio($request);
        $data = $request->validate(
            ['ai_text' => 'required|string|min:10|max:8000'],
            ['ai_text.required' => 'Dán đoạn chat hoặc ghi vài dòng về đơn để AI lên đơn giúp bạn.', 'ai_text.min' => 'Ghi thêm chút thông tin nữa nhé (khách là ai, cần làm gì…).'],
        );

        $this->allowLongRun();

        try {
            $draft = $assistant->draftOrder($studio, $data['ai_text']);
        } catch (AiException $e) {
            return back()->withInput($request->only('ai_text'))->withErrors(['ai_text' => $e->getMessage()]);
        }

        return redirect()->route('orders.create')
            ->withInput($draft['input'])
            ->with('ai_draft', ['missing' => $draft['missing'], 'matched' => $draft['matched'], 'model' => $draft['model']]);
    }

    // ------------------------------------------------------------------ Trong trang đơn

    public function assist(Request $request, Assistant $assistant, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $data = $request->validate([
            'task' => ['required', Rule::in(['message', 'brief', 'feedback'])],
            'situation' => ['required_if:task,message', 'nullable', Rule::in(array_keys(Assistant::SITUATIONS))],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('task') === 'message' && $request->input('situation') === 'custom')],
        ], ['note.required' => 'Ghi yêu cầu cho tin nhắn (VD: hẹn khách gọi lúc 8h tối để chốt màu).']);

        $back = redirect()->to(route('orders.show', $order->id).'#tro-ly-ai');
        $this->allowLongRun();

        try {
            $result = match ($data['task']) {
                'message' => ['title' => 'Tin nhắn: '.Assistant::SITUATIONS[$data['situation']]] + $assistant->clientMessage($order, $data['situation'], $data['note'] ?? null),
                'brief' => ['title' => 'Brief còn thiếu gì?', 'copy_title' => 'Tin nhắn hỏi khách'] + $assistant->briefCheck($order),
                'feedback' => ($r = $assistant->feedbackChecklist($order)) + ['title' => 'Việc cần sửa — vòng '.$r['round'], 'copy_title' => 'Danh sách để sao chép'],
            };
        } catch (AiException $e) {
            return $back->withInput()->with('ai_error', $e->getMessage());
        }

        return $back->withInput($request->only('situation', 'note'))->with('ai_result', $result);
    }

    // ------------------------------------------------------------------

    /**
     * Ghi thẳng bằng query: tránh trường hợp APP_KEY đã đổi, Eloquent cố giải mã giá trị cũ để so sánh rồi lỗi.
     * Mã hoá giống hệt cast "encrypted" của model (Crypt::encryptString).
     */
    private function storeKey(Studio $studio, ?string $key): void
    {
        Studio::query()->whereKey($studio->id)->update(['gemini_api_key' => $key !== null ? Crypt::encryptString($key) : null]);
    }

    /** Model thông minh có thể nghĩ 10–30 giây, cộng chuỗi dự phòng — xin thêm thời gian chạy (hosting có thể không cho). */
    private function allowLongRun(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('net.ai.total_budget', 75) + 30);
        }
    }
}
