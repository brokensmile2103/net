<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Báo giá (các dòng phí), gửi báo giá, ghi nhận thu tiền / cọc. */
class OrderMoneyController extends Controller
{
    use ResolvesStudio;

    public function charges(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);

        $data = $request->validate([
            'charges' => 'nullable|array|max:30',
            'charges.*.kind' => ['required', Rule::in(array_keys(config('net.charge_kinds')))],
            'charges.*.label' => 'nullable|string|max:160',
            'charges.*.amount' => 'nullable|numeric|min:0|max:10000000000',
            'deposit_percent' => 'required|integer|min:0|max:100',
            'send' => 'nullable|boolean',
        ]);

        // Cùng 1 transaction: báo giá bị từ chối (tổng < số đã thu) thì % cọc cũng không bị lưu dở.
        DB::transaction(function () use ($order, $service, $data) {
            $order->forceFill(['deposit_percent' => $data['deposit_percent']])->save();
            $service->setCharges($order, $data['charges'] ?? []);
        });

        if ($request->boolean('send')) {
            $service->sendQuote($order);

            return back()->with('status', 'Đã lưu và gửi báo giá — khách xem và bấm đồng ý ở link theo dõi.');
        }

        return back()->with('status', 'Đã lưu báo giá: '.money($order->total_amount).'đ.');
    }

    public function sendQuote(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $service->sendQuote($studio->orders()->findOrFail($order));

        return back()->with('status', 'Đã gửi báo giá — khách xem và bấm đồng ý ở link theo dõi.');
    }

    public function pay(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);

        $data = $request->validate([
            'amount' => 'required|numeric|gt:0|max:10000000000',
            'paid_on' => 'required|date|before_or_equal:today',
            'method' => ['required', Rule::in(array_keys(config('net.payment_methods')))],
            'note' => 'nullable|string|max:255',
        ]);

        $service->addPayment($order, (float) $data['amount'], Carbon::parse($data['paid_on']), $data['method'], $data['note'] ?? null);

        return back()->with('status', 'Đã ghi nhận '.money($data['amount']).'đ.'.($order->balance() <= 0 ? ' Đơn đã thu đủ.' : ''));
    }

    public function destroyPayment(Request $request, OrderService $service, int $payment): RedirectResponse
    {
        $studio = $this->studio($request);
        $service->deletePayment($studio->payments()->findOrFail($payment));

        return back()->with('status', 'Đã xoá khoản thu.');
    }
}
