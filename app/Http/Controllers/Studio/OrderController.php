<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
use App\Models\Studio;
use App\Services\BoardQuery;
use App\Services\ClientDirectory;
use App\Services\ImageService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    use ResolvesStudio;

    /** Danh sách đơn: lọc theo giai đoạn, tìm theo mã / tên đơn / tên khách. */
    public function index(Request $request): View
    {
        $studio = $this->studio($request);
        $statuses = config('net.statuses');
        $filter = $request->query('loc');
        $q = trim((string) $request->query('q'));

        $orders = $studio->orders()->with(['client', 'service'])
            ->when($filter === 'open', fn ($w) => $w->open())
            ->when($filter === 'overdue', fn ($w) => $w->open()->whereNotNull('due_on')->where('due_on', '<', Carbon::today()->toDateString()))
            ->when($filter === 'unpaid', fn ($w) => $w->where('status', '!=', 'cancelled')->whereColumn('paid_amount', '<', 'total_amount'))
            ->when(array_key_exists((string) $filter, $statuses), fn ($w) => $w->where('status', $filter))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('code', 'like', '%'.$q.'%')->orWhere('title', 'like', '%'.$q.'%')
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')->orWhere('facebook', 'like', '%'.$q.'%'))))
            ->orderByRaw("CASE WHEN status IN ('delivered', 'cancelled') THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_on')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('orders.index', compact('studio', 'orders', 'filter', 'q'));
    }

    /**
     * Bảng việc (kanban): mỗi giai đoạn 1 cột; cột "Đã bàn giao" chỉ hiện đơn bàn giao gần đây (7/30/90 ngày).
     * Mỗi cột tải trước một trang thẻ; cột ẩn / thu gọn không tải thẻ (mở ra mới tải) — chịu được hàng trăm đơn.
     */
    public function board(Request $request): View
    {
        $studio = $this->studio($request);
        $prefs = $studio->boardPrefs();

        // Đổi sắp xếp bằng ?sap-xep=… → nhớ luôn cho lần sau.
        $sort = (string) $request->query('sap-xep');
        if (array_key_exists($sort, config('net.board.sorts')) && $sort !== $prefs['sort']) {
            $prefs['sort'] = $sort;
            $studio->forceFill(['board_prefs' => $prefs])->save();
        }

        $board = BoardQuery::fromRequest($studio, $request, $prefs);
        $columns = collect(config('net.statuses'))->filter(fn ($s) => $s['board']);
        $totals = $board->totals();
        $cards = [];
        foreach ($columns as $key => $col) {
            $cards[$key] = in_array($key, $prefs['hidden'], true) || in_array($key, $prefs['collapsed'], true)
                ? collect()
                : $board->cards($key);
        }
        $services = $studio->services()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);

        return view('orders.board', compact('studio', 'columns', 'board', 'totals', 'cards', 'prefs', 'services'));
    }

    /** "Tải thêm" của 1 cột: trả HTML các thẻ tiếp theo (bỏ qua thẻ đã có trên cột) + số đơn còn lại. */
    public function boardMore(Request $request): JsonResponse
    {
        $studio = $this->studio($request);
        $columns = array_keys(array_filter(config('net.statuses'), fn ($s) => $s['board']));
        $data = $request->validate([
            'status' => ['required', Rule::in($columns)],
            'except' => 'nullable|array|max:3000',
            'except.*' => 'integer',
        ]);

        $board = BoardQuery::fromRequest($studio, $request);
        $except = array_values(array_unique(array_map('intval', $data['except'] ?? [])));
        $cards = $board->cards($data['status'], $except);
        $total = $board->totals()[$data['status']]['count'] ?? 0;
        $shown = $board->base()->where('status', $data['status'])->whereIn('id', $except ?: [0])->count() + $cards->count();

        return response()->json([
            'html' => $cards->map(fn ($order) => view('orders._card', ['order' => $order])->render())->implode(''),
            'loaded' => $cards->count(),
            'remaining' => max(0, $total - $shown),
        ]);
    }

    /** Lưu tuỳ chọn Bảng việc (chế độ xem, cột ẩn / thu gọn, giới hạn số đơn, số ngày cột "Đã bàn giao"). */
    public function boardPrefs(Request $request): JsonResponse
    {
        $studio = $this->studio($request);
        $columns = array_keys(array_filter(config('net.statuses'), fn ($s) => $s['board']));
        $data = $request->validate([
            'view' => ['sometimes', Rule::in(array_keys(config('net.board.views')))],
            'sort' => ['sometimes', Rule::in(array_keys(config('net.board.sorts')))],
            'hidden' => 'sometimes|array',
            'hidden.*' => [Rule::in($columns)],
            'collapsed' => 'sometimes|array',
            'collapsed.*' => [Rule::in($columns)],
            'limits' => 'sometimes|array',
            'limits.*' => 'nullable|integer|min:0|max:999',
            'delivered_days' => ['sometimes', 'integer', Rule::in(config('net.board.delivered_days'))],
        ]);

        if (isset($data['hidden']) && count(array_diff($columns, $data['hidden'])) === 0) {
            return response()->json(['message' => 'Phải để lại ít nhất 1 cột.', 'errors' => ['hidden' => ['Phải để lại ít nhất 1 cột.']]], 422);
        }

        $studio->forceFill(['board_prefs' => array_merge($studio->boardPrefs(), $data)])->save();

        return response()->json(['ok' => true, 'prefs' => $studio->fresh()->boardPrefs()]);
    }

    /** Lịch hạn chót theo tháng. */
    public function calendar(Request $request): View
    {
        $studio = $this->studio($request);
        $month = parse_month($request->query('thang'));
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $orders = $studio->orders()->with('client')->where('status', '!=', 'cancelled')
            ->whereBetween('due_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('due_on')->get()->groupBy(fn ($o) => $o->due_on->toDateString());

        $undated = $studio->orders()->open()->whereNull('due_on')->count();

        return view('orders.calendar', compact('studio', 'month', 'start', 'end', 'orders', 'undated'));
    }

    public function create(Request $request): View
    {
        $studio = $this->studio($request);
        $order = new Order([
            'priority' => 'normal',
            'revision_rounds' => $studio->default_revision_rounds,
            'deposit_percent' => $studio->default_deposit_percent,
            'lock_delivery' => $studio->lock_delivery_until_paid,
        ]);
        $order->client_id = (int) $request->query('khach') ?: null;

        return view('orders.form', $this->formData($studio, $order));
    }

    public function store(Request $request, OrderService $service, ImageService $images, ClientDirectory $directory): RedirectResponse
    {
        $studio = $this->studio($request);
        $data = $this->validated($request, $studio, true, $directory);
        $notice = null;

        if (! empty($data['client_id'])) {
            $client = $studio->clients()->findOrFail($data['client_id']);
        } else {
            // "Khách mới" nhưng trùng SĐT / email / Facebook với khách có sẵn → dùng luôn hồ sơ đó, bổ sung liên hệ còn trống.
            $contacts = ['phone' => $data['client_phone'] ?? null, 'email' => $data['client_email'] ?? null, 'facebook' => $data['client_facebook'] ?? null];
            $match = $directory->match($studio, $contacts['phone'], $contacts['email'], $contacts['facebook']);

            if ($match) {
                $client = $directory->fillMissing($match['client'], $contacts);
                $client->save();
                $notice = ' Đã gắn vào khách có sẵn “'.$client->name.'” (trùng '.ClientDirectory::matchLabel($match['by']).').';
            } else {
                $client = $studio->clients()->create(['name' => $data['client_name']] + $contacts);
            }
        }

        $svc = isset($data['service_id']) ? $studio->services()->find($data['service_id']) : null;
        $links = collect($data['links'] ?? [])->filter(fn ($l) => filled($l['url'] ?? null))->values()->all();

        $order = $service->create($studio, $client, $data, 'manual', $svc, $links, $data['status'] ?? 'in_progress');

        if ($request->hasFile('cover')) {
            $stored = $images->store($request->file('cover'), $studio, 'covers', (int) config('net.images.cover_max_px'));
            $order->forceFill(['cover_path' => $stored['path']])->save();
        }

        return redirect()->route('orders.show', $order->id)->with('status', 'Đã tạo đơn '.$order->code.'.'.$notice);
    }

    public function show(Request $request, int $order): View
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->with([
            'client', 'service', 'charges', 'links', 'payments',
            'rounds.proposals.images', 'rounds.feedback.image', 'feedback', 'events',
        ])->findOrFail($order);

        return view('orders.show', [
            'studio' => $studio,
            'order' => $order,
            'draft' => $order->rounds->firstWhere('status', 'draft'),
            'thread' => $order->feedback->whereNull('round_id')->values(),
            'qr' => $order->nextPaymentAmount() > 0
                ? vietqr_url($studio->bank_code, $studio->bank_account, $order->nextPaymentAmount(), $order->transferMemo(), $studio->bank_holder) : null,
            'message' => client_message($order),
        ]);
    }

    public function edit(Request $request, int $order): View
    {
        $studio = $this->studio($request);

        return view('orders.form', $this->formData($studio, $studio->orders()->with('links')->findOrFail($order)));
    }

    public function update(Request $request, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $data = $this->validated($request, $studio, false);

        $order->fill(collect($data)->only([
            'client_id', 'service_id', 'title', 'priority', 'brief', 'style', 'colors', 'specs', 'copy_text', 'budget_note',
            'due_on', 'client_due_on', 'revision_rounds', 'deposit_percent', 'internal_note',
        ])->all());
        $order->lock_delivery = (bool) ($data['lock_delivery'] ?? false);
        $order->save();

        return redirect()->route('orders.show', $order->id)->with('status', 'Đã lưu thông tin đơn.');
    }

    public function destroy(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $code = $order->code;
        $service->delete($order);

        return redirect()->route('orders.index')->with('status', 'Đã xoá đơn '.$code.'.');
    }

    /** Đổi giai đoạn: kéo thả trên Bảng việc (JSON) hoặc chọn ở trang đơn (form). */
    public function status(Request $request, OrderService $service, int $order): JsonResponse|RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('net.statuses')))],
            'reason' => 'nullable|string|max:250',
        ]);

        $service->changeStatus($order, $data['status'], $data['reason'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $order->status, 'label' => $order->statusLabel()]);
        }

        return back()->with('status', $order->code.': '.$order->statusLabel().'.');
    }

    /**
     * Nhận đơn mới và bắt đầu làm ngay — quy trình đơn giản, không bắt buộc gửi báo giá / chờ khách đồng ý.
     * Tuỳ chọn bỏ cọc: khách trả một lần sau khi duyệt.
     */
    public function accept(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        abort_unless(in_array($order->status, ['new', 'quoted'], true), 422, 'Đơn này đã được nhận.');

        if ($request->boolean('no_deposit') && (int) $order->deposit_percent !== 0) {
            $order->forceFill(['deposit_percent' => 0])->save();
        }

        $service->changeStatus($order, 'in_progress');

        return back()->with('status', 'Đã nhận đơn '.$order->code.' — bắt đầu làm thôi!');
    }

    public function cover(Request $request, ImageService $images, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $request->validate(['cover' => 'required|image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb')],
            ['cover.max' => 'Ảnh tối đa 15 MB.', 'cover.image' => 'File phải là ảnh (JPG, PNG, WebP).']);

        $stored = $images->store($request->file('cover'), $studio, 'covers', (int) config('net.images.cover_max_px'));
        $images->delete($studio, $order->cover_path);
        $order->forceFill(['cover_path' => $stored['path']])->save();

        return back()->with('status', 'Đã đổi ảnh bìa.');
    }

    public function removeCover(Request $request, ImageService $images, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        $images->delete($studio, $order->cover_path);
        $order->forceFill(['cover_path' => null])->save();

        return back()->with('status', 'Đã gỡ ảnh bìa.');
    }

    public function regenerateToken(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $service->regenerateToken($studio->orders()->findOrFail($order));

        return back()->with('status', 'Đã tạo link theo dõi mới. Link cũ không còn dùng được.');
    }

    // ---------------------------------------------------------------------

    private function formData(Studio $studio, Order $order): array
    {
        return [
            'studio' => $studio,
            'order' => $order,
            'clients' => $studio->clients()->orderBy('name')->get(['id', 'name', 'phone', 'email', 'facebook', 'company']),
            'services' => $studio->services()->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }

    private function validated(Request $request, Studio $studio, bool $creating, ?ClientDirectory $directory = null): array
    {
        $directory ??= app(ClientDirectory::class);
        // Liên hệ chỉ kiểm tra khi tạo đơn cho KHÁCH MỚI (chọn khách có sẵn thì các ô này bị tắt, không gửi lên;
        // chưa nhập tên thì chỉ cần báo 1 lỗi "chọn khách hoặc nhập tên").
        $newClient = $creating && ! $request->filled('client_id') && $request->filled('client_name');
        if ($newClient) {
            $directory->prepare($request, 'client_');
        }
        // "1.500.000", "1,500,000 đ" → 1500000.
        if ($creating && $request->filled('price')) {
            $request->merge(['price' => preg_replace('/\D+/', '', (string) $request->input('price')) ?: null]);
        }

        return $request->validate([
            'client_id' => [$creating ? 'nullable' : 'required', 'integer', Rule::exists('clients', 'id')->where('studio_id', $studio->id)],
            'client_name' => [$creating ? 'required_without:client_id' : 'nullable', 'nullable', 'string', 'max:100'],
        ] + ($newClient ? $directory->rules('client_') : []) + [
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('studio_id', $studio->id)],
            'title' => 'required|string|max:160',
            'price' => $creating ? 'nullable|integer|min:0|max:10000000000' : 'prohibited',
            'status' => ['nullable', Rule::in(['new', 'quoted', 'in_progress'])],
            'priority' => ['required', Rule::in(array_keys(config('net.priorities')))],
            'brief' => 'required|string|max:10000',
            'style' => 'nullable|string|max:500',
            'colors' => 'nullable|string|max:255',
            'specs' => 'nullable|string|max:500',
            'copy_text' => 'nullable|string|max:5000',
            'budget_note' => 'nullable|string|max:120',
            'due_on' => 'nullable|date',
            'client_due_on' => 'nullable|date',
            'revision_rounds' => 'required|integer|min:0|max:20',
            'deposit_percent' => 'required|integer|min:0|max:100',
            'lock_delivery' => 'nullable|boolean',
            'internal_note' => 'nullable|string|max:5000',
            'links' => 'nullable|array|max:10',
            'links.*.url' => 'nullable|url:http,https|max:2000',
            'links.*.label' => 'nullable|string|max:160',
            'cover' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb'),
        ], [
            'client_name.required_without' => 'Chọn khách có sẵn hoặc nhập tên khách mới.',
        ] + $directory->messages('client_') + [
            'links.*.url.url' => 'Link không hợp lệ — hãy dán đầy đủ, bắt đầu bằng https://',
            'brief.required' => 'Hãy ghi brief: khách cần thiết kế gì, dùng vào việc gì.',
            'price.integer' => 'Giá chỉ gồm chữ số, VD: 1500000.',
        ]);
    }
}
