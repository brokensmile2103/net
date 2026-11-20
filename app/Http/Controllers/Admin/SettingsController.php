<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\PlatformSetting;
use App\Services\Mail\PlatformMail;
use App\Services\Mail\StudioMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Super Admin — cài đặt cấp nền tảng: đăng ký công khai, máy chủ gửi email dùng chung (Resend). */
class SettingsController extends Controller
{
    public function edit(PlatformMail $mail, StudioMailer $mailer): View
    {
        $s = $mail->settings();

        return view('admin.settings', [
            'registrationOpen' => PlatformSetting::registrationOpen(),
            'mail' => $s,
            'mailSource' => $mail->source(),
            'maskedKey' => $s['resend_key'] ? substr($s['resend_key'], 0, 5).str_repeat('•', 8).substr($s['resend_key'], -4) : null,
            'platformToday' => $mailer->platformUsedToday(),
            'platformMonth' => EmailLog::query()->whereIn('status', EmailLog::COUNTED)->where('sent_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        PlatformSetting::put('registration_open', $request->boolean('registration_open') ? '1' : '0');

        return back()->with('status', 'Đã lưu cài đặt.');
    }

    /** Máy chủ gửi email của Nét (Resend). Key mới được kiểm tra với Resend trước khi lưu. */
    public function updateMail(Request $request, PlatformMail $mail): RedirectResponse
    {
        $back = redirect()->to(route('admin.settings.edit').'#email');
        if ($request->boolean('remove_key')) {
            $mail->save(['mail_resend_key' => null]);

            return $back->with('status', 'Đã gỡ API key Resend — Nét dùng cấu hình gửi thư trong .env (nếu có).');
        }

        $data = $request->validate([
            'resend_key' => ['nullable', 'string', 'max:200', 'regex:/^re_[A-Za-z0-9_\-]{8,}$/'],
            'from_address' => 'required|email|max:255',
            'from_name' => 'nullable|string|max:80',
            'per_studio_address' => 'nullable|boolean',
            'studio_daily' => 'required|integer|min:0|max:100000',
            'platform_daily' => 'required|integer|min:0|max:1000000',
        ], ['resend_key.regex' => 'API key Resend bắt đầu bằng re_…', 'from_address.required' => 'Nhập địa chỉ gửi trên tên miền đã xác thực ở Resend.']);

        $key = trim((string) ($data['resend_key'] ?? '')) ?: $mail->settings()['resend_key'];
        $notice = null;
        if ($key && (filled($data['resend_key'] ?? null) || PlatformMail::domainOf($data['from_address']) !== PlatformMail::domainOf($mail->settings()['from_address']))) {
            $check = $mail->verify($key, $data['from_address']);
            if (! $check['ok']) {
                return $back->withInput($request->except('resend_key'))->withErrors(['resend_key' => $check['message']]);
            }
            $notice = $check['message'];
        }

        $mail->save(array_filter([
            'mail_resend_key' => filled($data['resend_key'] ?? null) ? trim($data['resend_key']) : null,
        ]) + [
            'mail_from_address' => strtolower(trim($data['from_address'])),
            'mail_from_name' => trim((string) ($data['from_name'] ?? '')) ?: null,
            'mail_per_studio_address' => $request->boolean('per_studio_address') ? '1' : '0',
            'mail_studio_daily' => (string) (int) $data['studio_daily'],
            'mail_platform_daily' => (string) (int) $data['platform_daily'],
        ]);

        return $back->with('status', 'Đã lưu cấu hình email.'.($notice ? ' '.$notice : ''));
    }

    public function testMail(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('admin.settings.edit').'#email');
        $to = trim((string) $request->input('to', $request->user()->email));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $back->with('warning', 'Nhập email nhận thư thử.');
        }
        try {
            Mail::raw('Email thử từ Nét lúc '.now()->format('H:i d/m/Y').'. Cấu hình gửi thư đang hoạt động.', fn ($m) => $m->to($to)->subject('Nét: email thử'));
        } catch (\Throwable $e) {
            report($e);

            return $back->with('warning', 'Không gửi được: '.Str::limit($e->getMessage(), 200));
        }

        return $back->with('status', 'Đã gửi email thử tới '.$to.' (qua '.config('mail.default').').');
    }
}
