<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Studio;
use App\Notifications\ClientReminder;
use App\Notifications\StageUpdate;
use App\Services\Mail\StageMail;
use App\Services\Mail\StudioMailer;
use App\Support\EmailCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Email gửi khách của studio: cài đặt (tên người gửi, trả lời về đâu, chế độ thử, email theo giai đoạn),
 * xem trước theo mẫu giao diện, gửi thử, nhật ký; ở trang đơn: gửi email cập nhật tay, huỷ / gửi ngay email đang chờ.
 */
class EmailController extends Controller
{
    use ResolvesStudio;

    public function update(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        $stages = array_keys(config('net.stage_emails.stages'));
        $rules = [
            'sender_name' => 'nullable|string|max:80',
            'reply_to' => 'nullable|email|max:255',
            'sandbox' => 'nullable|boolean',
            'stages_on' => 'nullable|boolean',
            'signature' => 'nullable|string|max:300',
        ];
        foreach ($stages as $k) {
            $rules['stages.'.$k.'.on'] = 'nullable|boolean';
            $rules['stages.'.$k.'.note'] = 'nullable|string|max:500';
        }
        $data = $request->validate($rules, [], ['reply_to' => 'email nhận trả lời', 'stages.*.note' => 'lời nhắn']);

        $studio->mergeEmailPrefs([
            'sender_name' => trim((string) ($data['sender_name'] ?? '')),
            'reply_to' => trim((string) ($data['reply_to'] ?? '')),
            'sandbox' => (bool) ($data['sandbox'] ?? false),
            'stages_on' => (bool) ($data['stages_on'] ?? false),
            'signature' => trim((string) ($data['signature'] ?? '')),
            'stages' => collect($stages)->mapWithKeys(fn ($k) => [$k => [
                'on' => (bool) ($data['stages'][$k]['on'] ?? false),
                'note' => trim((string) ($data['stages'][$k]['note'] ?? '')),
            ]])->all(),
        ]);
        $p = $studio->emailPrefs();

        return redirect()->to(route('settings.index').'#email-khach')->with('status', match (true) {
            $p['sandbox'] => 'Đã lưu. Chế độ thử đang bật — mọi email gửi khách sẽ chuyển về '.($studio->email ?: 'email studio (hãy điền ở Hồ sơ studio)').'.',
            $p['stages_on'] => 'Đã lưu và bật email báo tiến độ cho khách.',
            default => 'Đã lưu cài đặt email.',
        });
    }

    /** Xem trước email (mở tab mới): giai đoạn đơn hoặc email nhắc, theo mẫu đang chọn hoặc ?mau=… */
    public function preview(Request $request): Response
    {
        $studio = $this->studio($request);
        $kind = (string) $request->query('loai', 'stage:review');
        [$group, $key] = array_pad(explode(':', $kind, 2), 2, '');
        $order = $this->sampleOrder($studio, $group === 'stage' ? $key : null);

        $n = match (true) {
            $group === 'stage' && array_key_exists($key, config('net.stage_emails.stages')) => new StageUpdate($order, $key, $studio->emailPrefs()['stages'][$key]['note'] ?: null, true),
            $group === 'reminder' && array_key_exists($key, config('net.reminders.kinds')) => new ClientReminder($order, $key, true),
            default => abort(404),
        };
        $n->theme = array_key_exists((string) $request->query('mau'), config('net.page_themes')) ? (string) $request->query('mau') : null;

        return response($n->preview())->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    /** Gửi 1 email mẫu (giai đoạn đang chọn) tới email của studio. Đếm vào hạn mức như email thật. */
    public function test(Request $request, StudioMailer $mailer): RedirectResponse
    {
        $studio = $this->studio($request);
        $back = redirect()->to(route('settings.index').'#email-khach');
        $stage = (string) $request->input('stage', 'review');
        abort_unless(array_key_exists($stage, config('net.stage_emails.stages')), 404);
        if (! EmailCheck::plausible($studio->email, false)) {
            return $back->with('warning', 'Hãy điền email thật của studio (Hồ sơ studio) để nhận email thử.');
        }

        $order = $this->sampleOrder($studio, $stage);
        $log = $mailer->deliver($studio, $order->exists ? $order : null, 'test', (string) $studio->email, $studio->name,
            new StageUpdate($order, $stage, $studio->emailPrefs()['stages'][$stage]['note'] ?: null, true), requirePlausible: false);

        return in_array($log->status, EmailLog::COUNTED, true)
            ? $back->with('status', 'Đã gửi email thử “'.config('net.stage_emails.stages.'.$stage.'.label').'” tới '.$studio->email.'.')
            : $back->with('warning', 'Chưa gửi được: '.$log->note);
    }

    public function log(Request $request): View
    {
        $studio = $this->studio($request);
        $status = (string) $request->query('trang-thai', '');

        return view('settings.email-log', [
            'studio' => $studio,
            'logs' => $studio->emailLogs()->with('order:id,code,title')
                ->when(in_array($status, ['sent', 'sandbox', 'pending', 'failed', 'skipped', 'cancelled'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'usedToday' => app(StudioMailer::class)->usedToday($studio),
            'limit' => app(\App\Services\Mail\PlatformMail::class)->studioLimit($studio),
        ]);
    }

    // ===== Trang đơn =====

    public function sendForOrder(Request $request, StageMail $outbox, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->with('client')->findOrFail($order);
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $back = redirect()->to(route('orders.show', $order->id).'#email-khach');

        if (! filter_var((string) $order->client?->email, FILTER_VALIDATE_EMAIL)) {
            return $back->with('warning', 'Khách chưa có email — thêm email trong hồ sơ khách trước.');
        }
        if (! array_key_exists($order->status, config('net.stage_emails.stages'))) {
            return $back->with('warning', 'Giai đoạn này chưa có mẫu email.');
        }
        $log = $outbox->queue($order, $data['note'] ?? null, manual: true);
        $log = $log ? $outbox->send($log) : null;

        return match (true) {
            ! $log => $back->with('warning', 'Không xếp được email.'),
            $log->status === 'sent' => $back->with('status', 'Đã gửi email “'.config('net.stage_emails.stages.'.$order->status.'.label').'” tới '.$order->client->email.'.'),
            $log->status === 'sandbox' => $back->with('status', 'Chế độ thử: email đã chuyển về '.$studio->email.' thay vì gửi khách.'),
            default => $back->with('warning', 'Chưa gửi được: '.$log->note),
        };
    }

    public function previewForOrder(Request $request, int $order): Response
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->with(['client', 'service', 'studio'])->findOrFail($order);
        $stage = array_key_exists($order->status, config('net.stage_emails.stages')) ? $order->status : 'in_progress';
        $note = trim((string) $request->query('note', '')) ?: ($studio->emailPrefs()['stages'][$stage]['note'] ?: null);

        return response((new StageUpdate($order, $stage, $note ? mb_substr($note, 0, 1000) : null, true))->preview())
            ->header('Content-Type', 'text/html; charset=UTF-8')->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    /** Huỷ hoặc gửi ngay 1 email đang chờ. */
    public function pending(Request $request, StageMail $outbox, int $log): RedirectResponse
    {
        $studio = $this->studio($request);
        $log = $studio->emailLogs()->where('status', 'pending')->findOrFail($log);
        $request->validate(['action' => ['required', Rule::in(['cancel', 'send'])]]);

        if ($request->input('action') === 'cancel') {
            $log->forceFill(['status' => 'cancelled', 'note' => 'Bạn đã huỷ trước khi gửi.'])->save();

            return back()->with('status', 'Đã huỷ email đang chờ.');
        }
        $log = $outbox->send($log);

        return in_array($log->status, EmailLog::COUNTED, true) ? back()->with('status', 'Đã gửi email tới '.$log->to_email.'.') : back()->with('warning', 'Chưa gửi: '.$log->note);
    }

    /** Đơn mẫu để xem trước: đơn mới nhất của studio, chưa có thì dựng đơn giả (không lưu). */
    private function sampleOrder(Studio $studio, ?string $stage): Order
    {
        $order = $studio->orders()->with(['client', 'service'])->latest('id')->first();
        if ($order) {
            $order->setRelation('studio', $studio);
            if ($stage) {
                $order->status = $stage; // chỉ để dựng nội dung — không lưu
            }

            return $order;
        }

        $order = new Order(['title' => 'Logo tiệm bánh Mây', 'deposit_percent' => 50, 'revision_rounds' => 2]);
        $order->forceFill(['code' => 'N0001', 'status' => $stage ?? 'review', 'tracking_token' => str_repeat('x', 40), 'total_amount' => 1500000, 'paid_amount' => 0, 'due_on' => now()->addDays(7)]);
        $order->setRelation('client', new Client(['name' => 'Khách mẫu']));
        $order->setRelation('studio', $studio);
        $order->setRelation('service', null);

        return $order;
    }

}
