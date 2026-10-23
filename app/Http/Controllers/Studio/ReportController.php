<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Báo cáo theo tháng: tiền thực thu, đơn nhận / bàn giao, đúng hạn, số vòng sửa, theo dịch vụ, khách quen. */
class ReportController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request): View
    {
        $studio = $this->studio($request);
        $month = parse_month($request->query('thang'));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $range = [$from->toDateString(), $to->toDateString()];

        $payments = $studio->payments()->whereBetween('paid_on', $range);
        $delivered = $studio->orders()->where('status', 'delivered')->whereBetween('delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->withCount(['rounds as sent_rounds' => fn ($r) => $r->where('status', '!=', 'draft')])->get();
        $withDue = $delivered->filter(fn ($o) => $o->due_on);
        $onTime = $withDue->filter(fn ($o) => $o->delivered_at->toDateString() <= $o->due_on->toDateString())->count();

        // 12 tháng gần nhất — tiền thực thu theo ngày thu.
        $trendFrom = $month->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $byMonth = $studio->payments()->whereBetween('paid_on', [$trendFrom->toDateString(), $range[1]])->get(['amount', 'paid_on'])
            ->groupBy(fn (Payment $p) => $p->paid_on->format('Y-m'))->map(fn ($g) => (float) $g->sum('amount'));
        $trend = collect(range(11, 0))->map(function ($i) use ($month, $byMonth) {
            $m = $month->copy()->subMonthsNoOverflow($i);

            return ['key' => $m->format('Y-m'), 'label' => 'T'.$m->month, 'year' => $m->year, 'value' => $byMonth[$m->format('Y-m')] ?? 0.0];
        });

        $byService = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->leftJoin('services', 'services.id', '=', 'orders.service_id')
            ->where('payments.studio_id', $studio->id)->whereBetween('payments.paid_on', $range)
            ->selectRaw("COALESCE(services.name, 'Khác') as name, SUM(payments.amount) as total, COUNT(DISTINCT orders.id) as orders")
            ->groupBy('services.name')->orderByDesc('total')->get();

        $topClients = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('payments.studio_id', $studio->id)->where('payments.paid_on', '>=', $month->copy()->subMonthsNoOverflow(11)->startOfMonth()->toDateString())
            ->where('payments.paid_on', '<=', $range[1])
            ->selectRaw('clients.id, clients.name, SUM(payments.amount) as total, COUNT(DISTINCT orders.id) as orders')
            ->groupBy('clients.id', 'clients.name')->orderByDesc('total')->limit(5)->get();

        return view('reports.index', [
            'studio' => $studio,
            'month' => $month,
            'r' => [
                'collected' => (float) (clone $payments)->sum('amount'),
                'payments' => (clone $payments)->count(),
                'new_orders' => $studio->orders()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'delivered' => $delivered->count(),
                'on_time_rate' => $withDue->count() ? (int) round($onTime / $withDue->count() * 100) : null,
                'avg_rounds' => $delivered->count() ? round($delivered->avg('sent_rounds'), 1) : null,
                'receivable' => (float) $studio->orders()->where('status', '!=', 'cancelled')->whereColumn('paid_amount', '<', 'total_amount')
                    ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as due')->value('due'),
                'open' => $studio->orders()->open()->count(),
            ],
            'trend' => $trend,
            'byService' => $byService,
            'topClients' => $topClients,
        ]);
    }

    /** Toàn bộ đơn ra CSV (mở bằng Excel). */
    public function export(Request $request): StreamedResponse
    {
        $studio = $this->studio($request);
        $orders = $studio->orders()->with(['client', 'service'])->orderBy('id')->get();
        $statuses = config('net.statuses');

        return response()->streamDownload(function () use ($orders, $statuses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Mã đơn', 'Tên đơn', 'Khách', 'SĐT', 'Email', 'Facebook', 'Dịch vụ', 'Giai đoạn', 'Ngày tạo', 'Hạn bàn giao', 'Ngày bàn giao', 'Tổng tiền', 'Đã thu', 'Còn lại', 'Link theo dõi']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->code, csv_safe($o->title), csv_safe($o->client?->name), csv_safe($o->client?->phone), csv_safe($o->client?->email), csv_safe($o->client?->facebook), csv_safe($o->service?->name),
                    $statuses[$o->status]['label'] ?? $o->status, $o->created_at->format('d/m/Y'), $o->due_on?->format('d/m/Y'), $o->delivered_at?->format('d/m/Y'),
                    round((float) $o->total_amount), round((float) $o->paid_amount), round(max(0, $o->balance())), $o->trackingUrl(),
                ]);
            }
            fclose($out);
        }, 'net-don-hang-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
