<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\OrderEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** "Hôm nay": việc cần làm ngay — đơn mới, trễ hạn, sắp tới hạn, khách vừa phản hồi, chờ khách quá lâu, tiền chưa thu. */
class TodayController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request): View
    {
        $studio = $this->studio($request);
        $today = Carbon::today();
        $open = $studio->orders()->open()->with('client')->get();

        $clientEvents = OrderEvent::query()
            ->whereIn('order_id', $studio->orders()->select('id'))
            ->where('actor', 'client')->where('type', '!=', 'created')
            ->where('created_at', '>=', now()->subDays(7))
            ->with('order.client')->latest('created_at')->limit(8)->get();

        $waitingDays = (int) config('net.waiting_reminder_days', 3);

        return view('today', [
            'studio' => $studio,
            'newOrders' => $open->where('status', 'new')->sortByDesc('created_at')->values(),
            'overdue' => $open->filter(fn ($o) => $o->due_on && $o->due_on->lt($today))->sortBy('due_on')->values(),
            'dueSoon' => $open->filter(fn ($o) => $o->due_on && $o->due_on->betweenIncluded($today, $today->copy()->addDays(7)))->sortBy('due_on')->values(),
            'revising' => $open->where('status', 'revising')->values(),
            'waiting' => $open->where('status', 'review')->filter(fn ($o) => ($o->status_changed_at ?? $o->updated_at)->lt(now()->subDays($waitingDays)))->values(),
            'clientEvents' => $clientEvents,
            'stats' => [
                'open' => $open->whereNotIn('status', ['new'])->count(),
                'collected' => (float) $studio->payments()->whereBetween('paid_on', [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()])->sum('amount'),
                'receivable' => (float) $studio->orders()->where('status', '!=', 'cancelled')->whereColumn('paid_amount', '<', 'total_amount')
                    ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as due')->value('due'),
            ],
            'waitingDays' => $waitingDays,
            'consults' => $studio->consultations()->where('status', 'booked')
                ->whereBetween('starts_at', [now()->subHour(), now()->addDays(3)->endOfDay()])->orderBy('starts_at')->limit(6)->get(),
            'isEmpty' => ! $studio->orders()->exists(),
        ]);
    }
}
