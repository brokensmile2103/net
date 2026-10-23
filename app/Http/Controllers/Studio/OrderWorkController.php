<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\OrderLink;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Link Google Drive, trao đổi với khách, đánh dấu góp ý đã xử lý, bàn giao. */
class OrderWorkController extends Controller
{
    use ResolvesStudio;

    public function addLink(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(config('net.link_kinds')))],
            'url' => 'required|url:http,https|max:2000',
            'label' => 'nullable|string|max:160',
        ], ['url.url' => 'Link không hợp lệ — hãy dán đầy đủ, bắt đầu bằng https://']);

        $order->links()->create($data + ['from_client' => false]);
        if ($data['kind'] === 'source') {
            $service->log($order, 'link', 'Thêm file bàn giao'.($order->deliveryUnlocked() ? '.' : ' (khách thấy sau khi thanh toán đủ).'));
        }

        return back()->with('status', 'Đã thêm link.');
    }

    /** Bật / tắt email nhắc tự động cho riêng đơn này. */
    public function reminders(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $muted = ! $request->boolean('on');
        $order->forceFill(['reminders_muted' => $muted])->save();
        $service->log($order, 'note', $muted ? 'Tắt email nhắc tự động cho đơn này.' : 'Bật lại email nhắc tự động cho đơn này.', 'designer', false);

        return back()->with('status', $muted ? 'Đã tắt nhắc tự động cho '.$order->code.'.' : 'Đã bật nhắc tự động cho '.$order->code.'.');
    }

    public function removeLink(Request $request, int $link): RedirectResponse
    {
        $studio = $this->studio($request);
        $link = OrderLink::query()->whereIn('order_id', $studio->orders()->select('id'))->findOrFail($link);
        $link->delete();

        return back()->with('status', 'Đã gỡ link.');
    }

    public function message(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $service->comment($order, 'designer', $data['body']);

        return redirect()->to(route('orders.show', $order->id).'#trao-doi')->with('status', 'Đã gửi — khách thấy ở trang theo dõi.');
    }

    public function resolve(Request $request, int $feedback): RedirectResponse
    {
        $studio = $this->studio($request);
        $fb = Feedback::query()->whereIn('order_id', $studio->orders()->select('id'))->findOrFail($feedback);
        $fb->forceFill(['resolved_at' => $fb->resolved_at ? null : now()])->save();

        return back();
    }

    public function deliver(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);

        $data = $request->validate([
            'links' => 'nullable|array|max:10',
            'links.*.url' => 'nullable|url:http,https|max:2000',
            'links.*.label' => 'nullable|string|max:160',
            'note' => 'nullable|string|max:2000',
        ], ['links.*.url.url' => 'Link không hợp lệ — hãy dán đầy đủ, bắt đầu bằng https://']);

        $service->deliver($order, $data['links'] ?? [], $data['note'] ?? null);

        return back()->with('status', $order->deliveryUnlocked()
            ? 'Đã bàn giao — khách tải file ở trang theo dõi.'
            : 'Đã bàn giao. File được khoá tới khi khách thanh toán đủ '.money($order->balance()).'đ.');
    }
}
