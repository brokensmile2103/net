<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Services\Ai\Gemini;
use App\Services\ImageService;
use App\Services\StudioExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request, Gemini $gemini): View
    {
        $studio = $this->studio($request);

        return view('settings.index', [
            'studio' => $studio,
            'works' => $studio->portfolioItems()->get(),
            'aiUsage' => $studio->hasAi() ? $gemini->usage($studio) : [],
            // Gợi ý khi chọn mô hình: danh sách key này gọi được (nếu đã biết) + mặc định của hệ thống.
            'aiModelOptions' => collect($studio->hasAi() ? ($gemini->cachedModels($studio) ?? []) : [])
                ->merge(config('net.ai.default_models'))->unique()->sort()->values()->all(),
            'aiResetAt' => $gemini->resetAt()->timezone(config('app.timezone')),
        ]);
    }

    /** Hồ sơ studio — hiện trên trang đặt đơn & trang theo dõi của khách. */
    public function updateProfile(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);
        if ($request->filled('phone')) {
            $request->merge(['phone' => normalize_phone($request->input('phone'))]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => RegisterController::slugRules($studio->id),
            'tagline' => 'nullable|string|max:160',
            'bio' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contact_url' => 'nullable|url:http,https|max:255',
        ], RegisterController::slugMessages() + ['contact_url.url' => 'Link không hợp lệ — dán đầy đủ, bắt đầu bằng https://']);

        $slugChanged = $data['slug'] !== $studio->slug;
        $studio->update($data);

        return back()->with('status', $slugChanged ? 'Đã lưu. Địa chỉ trang đặt đơn mới: '.$studio->orderFormUrl() : 'Đã lưu hồ sơ studio.');
    }

    public function avatar(Request $request, ImageService $images): RedirectResponse
    {
        $studio = $this->studio($request);

        if ($request->boolean('remove')) {
            $images->delete($studio, $studio->avatar_path);
            $studio->forceFill(['avatar_path' => null])->save();

            return back()->with('status', 'Đã gỡ ảnh đại diện.');
        }

        $request->validate(['avatar' => 'required|image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb')]);
        $stored = $images->store($request->file('avatar'), $studio, 'brand', (int) config('net.images.avatar_px'), true);
        $images->delete($studio, $studio->avatar_path);
        $studio->forceFill(['avatar_path' => $stored['path']])->save();

        return back()->with('status', 'Đã cập nhật ảnh đại diện.');
    }

    /** Trang đặt đơn + quy định làm việc + mặc định cho đơn mới. */
    public function updateOrderForm(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);

        $data = $request->validate([
            'order_form_enabled' => 'nullable|boolean',
            'order_form_note' => 'nullable|string|max:2000',
            'policy' => 'nullable|string|max:5000',
            'default_deposit_percent' => 'required|integer|min:0|max:100',
            'default_revision_rounds' => 'required|integer|min:0|max:20',
            'lock_delivery_until_paid' => 'nullable|boolean',
        ]);
        $data['order_form_enabled'] = (bool) ($data['order_form_enabled'] ?? false);
        $data['lock_delivery_until_paid'] = (bool) ($data['lock_delivery_until_paid'] ?? false);

        $studio->update($data);

        return back()->with('status', 'Đã lưu cài đặt nhận đơn.');
    }

    /** Nhắc khách tự động qua email. */
    public function updateReminders(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        $kinds = array_keys(config('net.reminders.kinds'));
        $rules = ['enabled' => 'nullable|boolean', 'max' => 'required|integer|min:1|max:5'];
        foreach ($kinds as $k) {
            $rules['kinds.'.$k.'.on'] = 'nullable|boolean';
            $rules['kinds.'.$k.'.days'] = 'required|integer|min:1|max:30';
        }
        $data = $request->validate($rules, [], ['max' => 'số lần nhắc', 'kinds.*.days' => 'số ngày']);

        $studio->forceFill(['reminder_prefs' => [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'max' => (int) $data['max'],
            'kinds' => collect($kinds)->mapWithKeys(fn ($k) => [$k => [
                'on' => (bool) ($data['kinds'][$k]['on'] ?? false),
                'days' => (int) $data['kinds'][$k]['days'],
            ]])->all(),
        ]])->save();

        return redirect()->to(route('settings.index').'#nhac-khach')->with('status', ($data['enabled'] ?? false) ? 'Đã bật nhắc khách tự động.' : 'Đã lưu — nhắc khách tự động đang tắt.');
    }

    /** Hẹn gọi tư vấn trên trang đặt đơn. */
    public function updateConsult(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'duration' => ['required', 'integer', \Illuminate\Validation\Rule::in(config('net.consult.durations'))],
            'days' => 'nullable|array',
            'days.*' => 'integer|between:1,7',
            'ranges' => 'nullable|array|max:'.config('net.consult.max_ranges', 3),
            'ranges.*.0' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'ranges.*.1' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'notice_hours' => 'required|integer|min:0|max:168',
            'ahead_days' => 'required|integer|min:1|max:60',
            'channels' => 'required|array|min:1',
            'channels.*' => [\Illuminate\Validation\Rule::in(array_keys(config('net.consult.channels')))],
            'note' => 'nullable|string|max:500',
        ], [
            'channels.required' => 'Chọn ít nhất 1 cách gọi.',
            'ranges.*.*.regex' => 'Giờ không hợp lệ (dạng 09:00).',
        ]);

        $ranges = collect($data['ranges'] ?? [])->map(fn ($r) => [$r[0] ?? null, $r[1] ?? null])
            ->filter(fn ($r) => $r[0] && $r[1])->values()->all();
        foreach ($ranges as [$a, $b]) {
            if ($a >= $b) {
                return back()->withInput()->withErrors(['ranges' => 'Khung giờ '.$a.'–'.$b.': giờ kết thúc phải sau giờ bắt đầu.']);
            }
        }
        $enabled = (bool) ($data['enabled'] ?? false);
        if ($enabled && (! $ranges || empty($data['days']))) {
            return back()->withInput()->withErrors(['ranges' => 'Để bật hẹn tư vấn, chọn ít nhất 1 ngày trong tuần và 1 khung giờ.']);
        }

        $studio->forceFill(['consult_prefs' => [
            'enabled' => $enabled,
            'duration' => (int) $data['duration'],
            'days' => array_values(array_unique(array_map('intval', $data['days'] ?? []))),
            'ranges' => $ranges,
            'notice_hours' => (int) $data['notice_hours'],
            'ahead_days' => (int) $data['ahead_days'],
            'channels' => array_values($data['channels']),
            'note' => trim((string) ($data['note'] ?? '')),
        ]])->save();

        return redirect()->to(route('settings.index').'#tu-van')->with('status', $enabled ? 'Đã bật hẹn gọi tư vấn trên trang đặt đơn.' : 'Đã lưu — hẹn gọi tư vấn đang tắt.');
    }

    /** Gửi thử 1 email nhắc (mẫu "phương án chờ xem") tới email của studio để xem trước. */
    public function testReminder(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        $back = redirect()->to(route('settings.index').'#nhac-khach');
        if (! \App\Support\EmailCheck::plausible($studio->email)) {
            return $back->with('warning', 'Hãy điền email thật của studio (Hồ sơ studio) để nhận email thử.');
        }
        if (! \App\Services\ReminderService::mailReady()) {
            return $back->with('warning', 'Máy chủ chưa cấu hình gửi email (MAIL_MAILER / RESEND_API_KEY trong .env) nên chưa gửi được.');
        }

        $order = $studio->orders()->latest('id')->first() ?? new \App\Models\Order(['title' => 'Logo tiệm bánh Mây']);
        if (! $order->exists) {
            $order->code = 'N0001';
            $order->tracking_token = str_repeat('x', 40);
            $order->setRelation('client', new \App\Models\Client(['name' => 'Khách mẫu']));
        }
        $order->setRelation('studio', $studio);
        try {
            \Illuminate\Support\Facades\Notification::route('mail', $studio->email)->notifyNow(new \App\Notifications\ClientReminder($order, 'review', true));
        } catch (\Throwable $e) {
            report($e);

            return $back->with('warning', 'Không gửi được email thử: '.\Illuminate\Support\Str::limit($e->getMessage(), 160));
        }

        return $back->with('status', 'Đã gửi email thử tới '.$studio->email.'.');
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);

        $data = $request->validate([
            'bank_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'bank_account' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 ]+$/'],
            'bank_holder' => 'nullable|string|max:100',
            'bank_memo' => 'nullable|string|max:50',
        ], [
            'bank_code.regex' => 'Mã ngân hàng chỉ gồm chữ và số, VD: VCB, MB, TCB, ACB.',
            'bank_account.regex' => 'Số tài khoản chỉ gồm chữ số.',
        ]);

        $studio->update([
            'bank_code' => isset($data['bank_code']) ? strtoupper($data['bank_code']) : null,
            'bank_account' => isset($data['bank_account']) ? preg_replace('/\s+/', '', $data['bank_account']) : null,
            'bank_holder' => isset($data['bank_holder']) ? Str::upper(Str::ascii($data['bank_holder'])) : null,
            // Rỗng = không điền sẵn nội dung (ngân hàng tự xử lý). Middleware đã đổi '' thành null.
            'bank_memo' => isset($data['bank_memo']) ? (\App\Models\Studio::cleanMemoTemplate($data['bank_memo']) ?: null) : null,
        ]);

        return back()->with('status', 'Đã lưu thông tin nhận chuyển khoản.');
    }

    public function export(Request $request, StudioExporter $exporter): StreamedResponse
    {
        $studio = $this->studio($request);
        $data = $exporter->export($studio);

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, 'net-'.$studio->slug.'-'.now()->format('Ymd').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
