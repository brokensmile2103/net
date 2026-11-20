<?php

namespace App\Services\Analytics;

use App\Models\Studio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu cho trang Thống kê: hiệu quả trang đặt đơn trong N ngày gần nhất (so với N ngày trước đó).
 * Mọi truy vấn đều lọc theo studio_id.
 */
class AnalyticsReport
{
    /**
     * @return array<string, mixed>
     */
    public function build(Studio $studio, int $days): array
    {
        $days = in_array($days, config('net.analytics.ranges'), true) ? $days : 30;
        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        $cur = $this->totals($studio, $from, $to);
        $prev = $this->totals($studio, $prevFrom, $prevTo);

        return [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'totals' => $cur,
            'previous' => $prev,
            'delta' => collect($cur)->map(fn ($v, $k) => $this->delta((float) $v, (float) ($prev[$k] ?? 0)))->all(),
            'daily' => $this->daily($studio, $from, $to),
            'sources' => $this->sources($studio, $from, $to),
            'devices' => $this->devices($studio, $from, $to),
            'services' => $this->services($studio, $from, $to),
            'funnel' => $this->funnel($studio, $from, $to, $cur),
            'has_any' => $studio->pageVisits()->exists(),
        ];
    }

    /** @return array{views: int, visitors: int, orders: int, consults: int, value: float, rate: float} */
    public function totals(Studio $studio, Carbon $from, Carbon $to): array
    {
        $v = DB::table('page_visits')->where('studio_id', $studio->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('count(*) as visitors, coalesce(sum(views), 0) as views')->first();
        $orders = $this->formOrders($studio, $from, $to);
        $count = (clone $orders)->count();
        $consults = $studio->consultations()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count();
        $visitors = (int) ($v->visitors ?? 0);

        return [
            'views' => (int) ($v->views ?? 0),
            'visitors' => $visitors,
            'orders' => $count,
            'consults' => $consults,
            'value' => (float) (clone $orders)->where('status', '!=', 'cancelled')->sum('total_amount'),
            'rate' => $visitors > 0 ? round(($count + $consults) / $visitors * 100, 1) : 0.0,
        ];
    }

    private function formOrders(Studio $studio, Carbon $from, Carbon $to)
    {
        return $studio->orders()->where('source', 'form')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /** % thay đổi so với kỳ trước; null khi kỳ trước = 0 (không so được). */
    private function delta(float $now, float $before): ?int
    {
        if ($before <= 0) {
            return null;
        }

        return (int) round(($now - $before) / $before * 100);
    }

    /** @return list<array{day: Carbon, visitors: int, views: int, orders: int}> */
    private function daily(Studio $studio, Carbon $from, Carbon $to): array
    {
        $visits = DB::table('page_visits')->where('studio_id', $studio->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->groupBy('day')->selectRaw('day, count(*) as visitors, sum(views) as views')->get()
            ->keyBy(fn ($r) => substr((string) $r->day, 0, 10));
        $orders = $this->formOrders($studio, $from, $to)->get(['created_at'])
            ->countBy(fn ($o) => $o->created_at->toDateString());

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $k = $d->toDateString();
            $out[] = ['day' => $d->copy(), 'visitors' => (int) ($visits[$k]->visitors ?? 0), 'views' => (int) ($visits[$k]->views ?? 0), 'orders' => (int) ($orders[$k] ?? 0)];
        }

        return $out;
    }

    /** @return list<array{key: string, label: string, icon: string, visitors: int, orders: int, rate: float}> */
    private function sources(Studio $studio, Carbon $from, Carbon $to): array
    {
        $visits = DB::table('page_visits')->where('studio_id', $studio->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->groupBy('source')->selectRaw('source, count(*) as visitors')->pluck('visitors', 'source');
        $orders = $this->formOrders($studio, $from, $to)->groupBy('ref_source')->selectRaw('ref_source, count(*) as n')->pluck('n', 'ref_source');
        $consults = $studio->consultations()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('ref_source')->selectRaw('ref_source, count(*) as n')->pluck('n', 'ref_source');

        $rows = [];
        foreach (config('net.analytics.sources') as $key => $meta) {
            // Đơn không rõ nguồn (đặt trước khi có thống kê, mở trực tiếp…) gom vào "không rõ".
            $o = (int) ($orders[$key] ?? 0) + (int) ($consults[$key] ?? 0)
                + ($key === 'direct' ? (int) ($orders[''] ?? 0) + (int) ($consults[''] ?? 0) : 0);
            $v = (int) ($visits[$key] ?? 0);
            if ($v === 0 && $o === 0) {
                continue;
            }
            $rows[] = ['key' => $key, 'label' => $meta['label'], 'icon' => $meta['icon'], 'visitors' => $v, 'orders' => $o, 'rate' => $v > 0 ? round($o / $v * 100, 1) : 0.0];
        }
        // pluck() đổi khoá null thành '' → cộng phần đó ở trên. Sắp theo người xem rồi theo đơn.
        usort($rows, fn ($a, $b) => [$b['visitors'], $b['orders']] <=> [$a['visitors'], $a['orders']]);

        return $rows;
    }

    /** @return list<array{key: string, label: string, visitors: int, share: int}> */
    private function devices(Studio $studio, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('page_visits')->where('studio_id', $studio->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->groupBy('device')->selectRaw('device, count(*) as visitors')->pluck('visitors', 'device');
        $total = max(1, (int) $rows->sum());

        return collect(config('net.analytics.devices'))->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'visitors' => (int) ($rows[$key] ?? 0), 'share' => (int) round(($rows[$key] ?? 0) / $total * 100),
        ])->values()->all();
    }

    /** Dịch vụ khách chọn nhiều nhất khi đặt qua trang. @return list<array{name: string, orders: int}> */
    private function services(Studio $studio, Carbon $from, Carbon $to): array
    {
        return $this->formOrders($studio, $from, $to)->with('service:id,name')->get(['id', 'service_id'])
            ->groupBy(fn ($o) => $o->service?->name ?? 'Chưa chọn dịch vụ')
            ->map(fn ($g, $name) => ['name' => $name, 'orders' => $g->count()])
            ->sortByDesc('orders')->take(6)->values()->all();
    }

    /** Phễu: xem trang → gửi đơn → bạn nhận làm → bàn giao. */
    private function funnel(Studio $studio, Carbon $from, Carbon $to, array $totals): array
    {
        $orders = $this->formOrders($studio, $from, $to);
        $started = (clone $orders)->whereNotIn('status', ['new', 'cancelled'])->count();
        $delivered = (clone $orders)->where('status', 'delivered')->count();

        return [
            ['label' => 'Người xem trang', 'value' => $totals['visitors']],
            ['label' => 'Gửi đơn', 'value' => $totals['orders']],
            ['label' => 'Bạn nhận làm', 'value' => $started],
            ['label' => 'Đã bàn giao', 'value' => $delivered],
        ];
    }
}
