<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Truy vấn Bảng việc dùng chung cho trang bảng và các lần "Tải thêm" của từng cột.
 *
 * Bảng việc không tải hết đơn một lượt: mỗi cột chỉ tải {@see pageSize()} thẻ đầu, số đơn và tổng tiền của cột
 * lấy bằng 1 câu GROUP BY (nên vẫn đúng dù cột có hàng trăm đơn). "Tải thêm" gửi danh sách id đang có trên cột
 * (except) thay vì offset — thẻ vừa được kéo vào/ra không làm lệch trang, không trùng, không sót.
 */
class BoardQuery
{
    /** @var array{q: string, filters: list<string>, service: ?int} */
    public array $criteria;

    public function __construct(public Studio $studio, public array $prefs)
    {
        $this->criteria = ['q' => '', 'filters' => [], 'service' => null];
    }

    public static function fromRequest(Studio $studio, Request $request, ?array $prefs = null): self
    {
        $board = new self($studio, $prefs ?? $studio->boardPrefs());
        $filters = array_values(array_intersect(array_keys(config('net.board.filters')), array_map('strval', (array) $request->input('loc', []))));
        $service = (int) $request->input('dv');
        $board->criteria = [
            'q' => mb_substr(trim((string) $request->input('q')), 0, 100),
            'filters' => $filters,
            'service' => $service > 0 && $studio->services()->whereKey($service)->exists() ? $service : null,
        ];

        return $board;
    }

    public function pageSize(): int
    {
        return max(5, (int) config('net.board.page_size', 40));
    }

    public function isFiltered(): bool
    {
        return $this->criteria['q'] !== '' || $this->criteria['filters'] || $this->criteria['service'];
    }

    /** Tham số URL của bộ lọc hiện tại (để giữ khi đổi sắp xếp, khi "Tải thêm"). */
    public function queryParams(): array
    {
        return array_filter(['q' => $this->criteria['q'], 'loc' => $this->criteria['filters'], 'dv' => $this->criteria['service']]);
    }

    /** Đơn thuộc Bảng việc (đang chạy + bàn giao gần đây) đã áp bộ lọc. */
    public function base(): Builder
    {
        $today = Carbon::today()->toDateString();
        $c = $this->criteria;
        $days = $this->prefs['delivered_days'];

        $query = $this->studio->orders()->getQuery()
            ->where(fn ($w) => $w->whereIn('status', Order::openStatuses())
                ->orWhere(fn ($d) => $d->where('status', 'delivered')->where('delivered_at', '>=', now()->subDays($days))));

        if ($c['q'] !== '') {
            $like = '%'.addcslashes($c['q'], '%_\\').'%';
            $query->where(fn ($s) => $s->where('code', 'like', $like)->orWhere('title', 'like', $like)
                ->orWhereHas('client', fn ($k) => $k->where('name', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)->orWhere('facebook', 'like', $like)));
        }
        foreach ($c['filters'] as $f) {
            match ($f) {
                'gap' => $query->where('priority', 'urgent'),
                'tre-han' => $query->whereIn('status', Order::openStatuses())->whereNotNull('due_on')->where('due_on', '<', $today),
                'sap-han' => $query->whereIn('status', Order::openStatuses())->whereBetween('due_on', [$today, Carbon::today()->addDays(7)->toDateString()]),
                'con-no' => $query->whereColumn('paid_amount', '<', 'total_amount'),
                'dung-yen' => $query->whereIn('status', Order::openStatuses())->where(fn ($w) => $w
                    ->where(fn ($r) => $r->where('status', 'review')->where('status_changed_at', '<=', now()->subDays(Order::staleDaysFor('review'))))
                    ->orWhere(fn ($r) => $r->where('status', '!=', 'review')->where('status_changed_at', '<=', now()->subDays(Order::staleDaysFor('new'))))),
                'chua-han' => $query->whereNull('due_on'),
                default => null,
            };
        }
        if ($c['service']) {
            $query->where('service_id', $c['service']);
        }

        return $query;
    }

    /** @return array<string, array{count: int, sum: float}> số đơn + tổng báo giá mỗi giai đoạn (đã lọc). */
    public function totals(): array
    {
        return $this->base()->reorder()->select('status', DB::raw('COUNT(*) as n'), DB::raw('COALESCE(SUM(total_amount), 0) as s'))
            ->groupBy('status')->get()
            ->mapWithKeys(fn ($r) => [$r->status => ['count' => (int) $r->n, 'sum' => (float) $r->s]])->all();
    }

    /** @param list<int> $except id đã có trên cột @return Collection<int, Order> */
    public function cards(string $status, array $except = [], ?int $limit = null): Collection
    {
        $query = $this->base()
            ->with('client')
            ->withExists(['links as has_source_link' => fn ($q) => $q->where('kind', 'source')])
            ->where('status', $status)
            ->when($except, fn ($q) => $q->whereNotIn('id', $except));

        $this->applySort($query);

        return $query->limit($limit ?? $this->pageSize())->get();
    }

    private function applySort(Builder $q): void
    {
        $sort = $this->prefs['sort'];
        if ($sort === 'priority') {
            $q->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END");
        }
        match ($sort) {
            'stalled' => $q->orderBy('status_changed_at'),
            'recent' => $q->orderByDesc('updated_at'),
            'newest' => $q->orderByDesc('id'),
            'amount' => $q->orderByDesc('total_amount'),
            default => $q->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END')->orderBy('due_on'), // priority, due
        };
        $q->orderByDesc('id');
    }
}
