<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Studio;
use App\Notifications\ConsultationBooked;
use App\Services\ClientDirectory;
use App\Services\ConsultSlots;
use App\Services\FormGuard;
use App\Services\ReminderService;
use App\Support\EmailCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Khách đặt lịch gọi tư vấn trên trang đặt đơn (/dat/{slug}#tu-van) — chỉ khi studio bật.
 * Trang lịch hẹn riêng /hen/{token}: xem lại, thêm vào lịch, huỷ.
 */
class ConsultationController extends Controller
{
    public function store(Request $request, ConsultSlots $slots, ClientDirectory $directory, string $slug): RedirectResponse
    {
        $studio = Studio::query()->where('slug', $slug)->first();
        abort_unless($studio && $studio->is_active && $studio->consultEnabled(), 404);
        $back = fn (array $errors) => redirect()->to(route('public.order', $slug).'#tu-van')->withInput()->withErrors($errors, 'consult');

        if (! FormGuard::verify($request, 'consult-'.$studio->id, $request->input('form_ts'), $request->input('form_sig'), $request->input('hp_key'))) {
            return $back(['slot' => 'Phiên đặt lịch đã hết hạn. Vui lòng tải lại trang và chọn lại giờ.']);
        }

        $prefs = $studio->consultPrefs();
        $directory->prepare($request, 'c_');
        $validator = validator($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'c_name' => 'required|string|max:100',
            'channel' => ['required', Rule::in($prefs['channels'])],
            'c_note' => 'nullable|string|max:1000',
        ] + $directory->rules('c_'), $directory->messages('c_') + [
            'date.required' => 'Hãy chọn ngày.',
            'time.required' => 'Hãy chọn giờ gọi.',
            'c_name.required' => 'Hãy cho biết tên của bạn.',
            'channel.required' => 'Hãy chọn cách gọi.',
        ]);
        if ($validator->fails()) {
            return $back($validator->errors()->toArray());
        }
        $data = $validator->validated();

        // Gọi qua Zalo / điện thoại thì cần số điện thoại.
        if (in_array($data['channel'], ['zalo', 'phone'], true) && blank($data['c_phone'] ?? null)) {
            return $back(['c_phone' => 'Để lại số điện thoại để '.$studio->name.' gọi cho bạn.']);
        }

        // Mỗi người chỉ giữ 1 lịch hẹn sắp tới (chống đặt trùng / giữ chỗ hàng loạt).
        $existing = $studio->consultations()->where('status', 'booked')->where('starts_at', '>', now())
            ->where(fn ($q) => $q->when($data['c_phone'] ?? null, fn ($w, $p) => $w->orWhere('phone', $p))
                ->when($data['c_email'] ?? null, fn ($w, $e) => $w->orWhere('email', $e)))
            ->when(blank($data['c_phone'] ?? null) && blank($data['c_email'] ?? null), fn ($q) => $q->whereRaw('1 = 0'))
            ->first();
        if ($existing) {
            return $back(['slot' => 'Bạn đã có lịch hẹn '.$existing->whenLabel().'. Huỷ lịch đó (link trong email / trang lịch hẹn) rồi đặt lại giúp mình nhé.']);
        }

        $consultation = DB::transaction(function () use ($studio, $slots, $data, $prefs, $directory) {
            // Khoá theo studio để 2 người không cùng giữ 1 giờ.
            Studio::query()->whereKey($studio->id)->lockForUpdate()->first();
            if (! $slots->isAvailable($studio, $data['date'], $data['time'])) {
                return null;
            }
            $contacts = ['phone' => $data['c_phone'] ?? null, 'email' => $data['c_email'] ?? null, 'facebook' => $data['c_facebook'] ?? null];
            $match = $directory->match($studio, $contacts['phone'], $contacts['email'], $contacts['facebook']);
            $client = $match['client'] ?? $studio->clients()->make(['name' => $data['c_name']]);
            $directory->fillMissing($client, $contacts);
            $client->studio_id = $studio->id;
            $client->save();

            $c = new Consultation($contacts + [
                'name' => $data['c_name'], 'channel' => $data['channel'], 'note' => $data['c_note'] ?? null,
                'starts_at' => Carbon::parse($data['date'].' '.$data['time']), 'duration' => $prefs['duration'],
            ]);
            $c->studio_id = $studio->id;
            $c->client_id = $client->id;
            $c->save();

            return $c;
        });

        if (! $consultation) {
            return $back(['slot' => 'Giờ này vừa có người đặt hoặc không còn trống — bạn chọn giờ khác giúp nhé.']);
        }

        $this->notify($consultation);

        return redirect()->route('public.consult.show', $consultation->token)->with('booked', true);
    }

    public function show(string $token): Response
    {
        $c = $this->find($token);

        return response()->view('public.consultation', ['c' => $c, 'studio' => $c->studio])
            ->header('Cache-Control', 'no-store, private');
    }

    public function ics(string $token): Response
    {
        $c = $this->find($token);

        return response($c->ics('Gọi tư vấn với '.$c->studio->name, $c->channelLabel()."\n".$c->publicUrl()), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="lich-hen-'.$c->starts_at->format('Ymd-Hi').'.ics"',
        ]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $c = $this->find($token);
        if ($c->isUpcoming()) {
            $c->forceFill(['status' => 'cancelled'])->save();
        }

        return redirect()->route('public.consult.show', $token)->with('status', 'Đã huỷ lịch hẹn. Bạn có thể đặt giờ khác trên trang đặt đơn.');
    }

    private function find(string $token): Consultation
    {
        $c = Consultation::query()->where('token', $token)->with('studio')->first();
        abort_unless($c && $c->studio?->is_active, 404);

        return $c;
    }

    /** Email xác nhận cho khách (nếu email có vẻ thật) + báo studio. Lỗi gửi mail không làm hỏng việc đặt lịch. */
    private function notify(Consultation $c): void
    {
        if (! ReminderService::mailReady()) {
            return;
        }
        try {
            if (EmailCheck::plausible($c->email)) {
                Notification::route('mail', [$c->email => $c->name])->notifyNow(new ConsultationBooked($c, 'client'));
            }
            if (EmailCheck::plausible($c->studio->email, false)) {
                Notification::route('mail', $c->studio->email)->notifyNow(new ConsultationBooked($c, 'studio'));
            }
        } catch (\Throwable $e) {
            Log::warning('Nét: không gửi được email lịch hẹn — '.$e->getMessage());
        }
    }
}
