<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Trang theo dõi đơn của khách — mở bằng link bí mật 40 ký tự. Khách xem tiến độ, báo giá, phương án;
 * chọn phương án, ghim góp ý lên ảnh, duyệt hoặc yêu cầu sửa; xem cách thanh toán; tải file bàn giao.
 * KHÔNG hiện ghi chú nội bộ, góp ý nội bộ hay vòng nháp.
 */
class TrackingController extends Controller
{
    public function show(string $token): Response
    {
        $order = $this->find($token)->load([
            'studio', 'client', 'service', 'charges', 'links', 'payments',
            'rounds' => fn ($r) => $r->where('status', '!=', 'draft'),
            'rounds.proposals.images', 'rounds.feedback',
            'feedback' => fn ($f) => $f->whereNull('round_id'),
            'events' => fn ($e) => $e->where('visible', true),
        ]);
        $studio = $order->studio;
        $next = $order->nextPaymentAmount();

        return response()->view('public.track', [
            'order' => $order,
            'studio' => $studio,
            'awaiting' => $order->rounds->firstWhere('status', 'sent'),
            'qr' => $next > 0 && ! $order->isCancelled() ? vietqr_url($studio->bank_code, $studio->bank_account, $next, $order->transferMemo(), $studio->bank_holder) : null,
            'nextAmount' => $next,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    public function acceptQuote(OrderService $service, string $token): RedirectResponse
    {
        $service->acceptQuote($this->find($token));

        return $this->back($token, 'Cảm ơn bạn đã xác nhận báo giá!', 'bao-gia');
    }

    public function choose(Request $request, OrderService $service, string $token): RedirectResponse
    {
        $data = $request->validate(['proposal_id' => 'required|integer']);
        $proposal = $service->clientChoose($this->find($token), (int) $data['proposal_id']);

        return $this->back($token, 'Bạn đã chọn '.$proposal->label.'.', 'phuong-an');
    }

    public function respond(Request $request, OrderService $service, string $token): RedirectResponse
    {
        $order = $this->find($token);
        $data = $request->validate([
            'decision' => 'required|in:approve,changes',
            'note' => 'nullable|string|max:3000',
            'pins' => 'nullable|array|max:30',
            'pins.*.image_id' => 'required|integer',
            'pins.*.x' => 'required|numeric|min:0|max:100',
            'pins.*.y' => 'required|numeric|min:0|max:100',
            'pins.*.body' => 'nullable|string|max:1000',
        ]);

        $service->clientRespond($order, $data['decision'], $data['note'] ?? null, $data['pins'] ?? []);

        return $this->back($token, $data['decision'] === 'approve'
            ? 'Cảm ơn bạn đã duyệt! '.$order->studio->name.' sẽ chuẩn bị file bàn giao.'
            : 'Đã gửi góp ý — '.$order->studio->name.' sẽ chỉnh sửa và gửi lại sớm.', 'phuong-an');
    }

    public function message(Request $request, OrderService $service, string $token): RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);
        $service->comment($this->find($token), 'client', $data['body']);

        return $this->back($token, 'Đã gửi lời nhắn.', 'trao-doi');
    }

    /** Link "Không nhận email nhắc cho đơn này" trong email nhắc (link có chữ ký, không đoán được). */
    public function mute(Request $request, OrderService $service, string $token): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Link đã hết hạn hoặc không hợp lệ.');
        $order = $this->find($token);
        if (! $order->reminders_muted) {
            $order->forceFill(['reminders_muted' => true])->save();
            $service->log($order, 'note', 'Khách bấm “không nhận email nhắc” — Nét ngừng tự gửi email nhắc cho đơn này.', 'system', false);
        }

        return redirect()->route('public.track', $token)->with('status', 'Đã tắt email nhắc tự động cho đơn này. Bạn vẫn xem đơn bình thường qua link này.');
    }

    private function find(string $token): Order
    {
        $order = Order::query()->where('tracking_token', $token)->with('studio')->first();
        abort_unless($order && $order->studio?->is_active, 404);

        return $order;
    }

    private function back(string $token, string $message, string $anchor): RedirectResponse
    {
        return redirect()->to(route('public.track', $token).'#'.$anchor)->with('status', $message);
    }
}
