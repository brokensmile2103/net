<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderService;

/** Bảng việc v5: tải theo trang, đếm bằng máy chủ, lọc, sắp xếp, tuỳ chọn cột / chế độ xem, "đứng yên"; tìm nhanh Ctrl + K. */
class BoardV5Test extends NetTestCase
{
    private function seedOrders($studio, int $n, string $status = 'in_progress', array $extra = []): array
    {
        $client = $studio->clients()->firstOrCreate(['name' => 'Chị Hà'], ['phone' => '0900000'.str_pad((string) $studio->id, 3, '0', STR_PAD_LEFT)]);
        $svc = app(OrderService::class);
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $o = $svc->create($studio, $client, ['title' => 'Đơn '.$status.' '.$i, 'brief' => 'B'] + $extra, 'manual', null, [], $status);
            $svc->setCharges($o, [['kind' => 'base', 'label' => 'Phí', 'amount' => 100000]]);
            $out[] = $o;
        }

        return $out;
    }

    public function test_columns_load_one_page_but_count_everything(): void
    {
        config(['net.board.page_size' => 5]);
        $studio = $this->makeStudio('nhieu-don');
        $orders = $this->seedOrders($studio, 12);
        $this->actingAs($studio->owner);

        $res = $this->get(route('orders.board'))->assertOk();
        $html = $res->getContent();
        $this->assertSame(5, substr_count($html, 'data-status="in_progress"'), 'Chỉ tải 1 trang thẻ.');
        $this->assertSame(['count' => 12, 'sum' => 1200000.0], $res->viewData('totals')['in_progress'], 'Số đơn + tổng tiền đếm đủ 12 đơn.');
        $this->assertCount(5, $res->viewData('cards')['in_progress']);

        // Tải thêm: bỏ qua các thẻ đã có, không trùng, đếm đúng phần còn lại.
        preg_match_all('/data-card data-id="(\d+)"/', $html, $m);
        $first = array_map('intval', $m[1]);
        $more = $this->postJson(route('orders.board.more'), ['status' => 'in_progress', 'except' => $first])->assertOk()->json();
        $this->assertSame(5, $more['loaded']);
        $this->assertSame(2, $more['remaining']);
        preg_match_all('/data-card data-id="(\d+)"/', $more['html'], $m2);
        $this->assertEmpty(array_intersect($first, array_map('intval', $m2[1])));

        $last = $this->postJson(route('orders.board.more'), ['status' => 'in_progress', 'except' => array_merge($first, array_map('intval', $m2[1]))])->json();
        $this->assertSame(2, $last['loaded']);
        $this->assertSame(0, $last['remaining']);

        // Cột lạ / studio khác.
        $this->postJson(route('orders.board.more'), ['status' => 'cancelled'])->assertStatus(422);
        $this->actingAs($this->makeStudio('khac-bang')->owner);
        $this->assertSame(0, $this->postJson(route('orders.board.more'), ['status' => 'in_progress'])->json('loaded'));
        $this->assertCount(12, $orders);
    }

    public function test_filters_search_and_sort(): void
    {
        $studio = $this->makeStudio('loc-bang');
        [$late] = $this->seedOrders($studio, 1, 'in_progress', ['due_on' => now()->subDays(3)->toDateString()]);
        [$urgent] = $this->seedOrders($studio, 1, 'review', ['priority' => 'urgent']);
        [$plain] = $this->seedOrders($studio, 1, 'revising');
        $late->forceFill(['title' => 'Logo quán Gió'])->save();
        $this->actingAs($studio->owner);

        $this->get(route('orders.board', ['loc' => ['tre-han']]))->assertOk()
            ->assertSee('data-id="'.$late->id.'"', false)->assertDontSee('data-id="'.$urgent->id.'"', false)->assertSee('1 đơn khớp');
        $this->get(route('orders.board', ['loc' => ['gap']]))->assertSee('data-id="'.$urgent->id.'"', false)->assertDontSee('data-id="'.$late->id.'"', false);
        $this->get(route('orders.board', ['q' => 'quán gió']))->assertSee('data-id="'.$late->id.'"', false)->assertDontSee('data-id="'.$plain->id.'"', false);
        $this->get(route('orders.board', ['q' => $plain->code]))->assertSee('data-id="'.$plain->id.'"', false);
        $this->get(route('orders.board', ['loc' => ['khong-co-that']]))->assertOk()->assertSee('data-id="'.$plain->id.'"', false);

        // Đứng yên lâu: đơn nằm ở "Đang sửa" 9 ngày.
        Order::query()->whereKey($plain->id)->update(['status_changed_at' => now()->subDays(9)]);
        $this->get(route('orders.board', ['loc' => ['dung-yen']]))->assertSee('data-id="'.$plain->id.'"', false)->assertDontSee('data-id="'.$late->id.'"', false)
            ->assertSee('9 ngày');

        // Đổi sắp xếp → nhớ cho lần sau; giá trị lạ bị bỏ qua.
        $this->get(route('orders.board', ['sap-xep' => 'amount']))->assertOk();
        $this->assertSame('amount', $studio->fresh()->boardPrefs()['sort']);
        $this->get(route('orders.board', ['sap-xep' => 'bay-mau']))->assertOk();
        $this->assertSame('amount', $studio->fresh()->boardPrefs()['sort']);
    }

    public function test_prefs_hide_collapse_view_limits(): void
    {
        $studio = $this->makeStudio('tuy-chon');
        [$o] = $this->seedOrders($studio, 1, 'quoted');
        $this->actingAs($studio->owner);

        $this->patchJson(route('orders.board.prefs'), ['view' => 'compact', 'hidden' => ['quoted'], 'collapsed' => ['new'], 'limits' => ['in_progress' => 5, 'review' => 0], 'delivered_days' => 90])->assertOk()
            ->assertJsonPath('prefs.view', 'compact')->assertJsonPath('prefs.limits', ['in_progress' => 5]);
        $prefs = $studio->fresh()->boardPrefs();
        $this->assertSame(['quoted'], $prefs['hidden']);
        $this->assertSame(['new'], $prefs['collapsed']);
        $this->assertSame(90, $prefs['delivered_days']);

        // Cột ẩn không tải thẻ (vẫn đếm), chế độ xem đúng.
        $res = $this->get(route('orders.board'))->assertOk()->assertSee('data-view="compact"', false)->assertDontSee('data-id="'.$o->id.'"', false);
        $this->assertSame(1, $res->viewData('totals')['quoted']['count']);
        $this->assertCount(0, $res->viewData('cards')['quoted']);

        $this->patchJson(route('orders.board.prefs'), ['view' => 'gallery'])->assertStatus(422);
        $this->patchJson(route('orders.board.prefs'), ['hidden' => ['canceled']])->assertStatus(422);
        $all = array_keys(array_filter(config('net.statuses'), fn ($s) => $s['board']));
        $this->patchJson(route('orders.board.prefs'), ['hidden' => $all])->assertStatus(422);

        // Giá trị hỏng trong DB → về mặc định, không lỗi trang.
        $studio->forceFill(['board_prefs' => ['view' => 'x', 'hidden' => 'abc', 'limits' => ['x' => 3], 'delivered_days' => 12]])->save();
        $this->assertSame(['view' => 'cards', 'sort' => 'priority', 'hidden' => [], 'collapsed' => [], 'limits' => [], 'delivered_days' => 30], $studio->fresh()->boardPrefs());
        $this->get(route('orders.board'))->assertOk();
    }

    public function test_status_changed_at_tracks_every_status_change(): void
    {
        $studio = $this->makeStudio('dung-yen');
        [$o] = $this->seedOrders($studio, 1, 'in_progress');
        $this->assertNotNull($o->status_changed_at);

        Order::query()->whereKey($o->id)->update(['status_changed_at' => now()->subDays(10)]);
        $o->refresh();
        $this->assertTrue($o->isStalled());
        $this->assertSame(10, $o->daysInStatus());

        // Sửa thông tin khác không tính là đổi giai đoạn.
        $o->forceFill(['title' => 'Đổi tên'])->save();
        $this->assertSame(10, $o->fresh()->daysInStatus());

        app(OrderService::class)->changeStatus($o->fresh(), 'review');
        $this->assertSame(0, $o->fresh()->daysInStatus());
        $this->assertFalse($o->fresh()->isStalled());
    }

    public function test_quick_search_is_scoped_and_puts_exact_code_first(): void
    {
        $studio = $this->makeStudio('tim-nhanh');
        $orders = $this->seedOrders($studio, 3, 'in_progress');
        $studio->clients()->create(['name' => 'Anh Khoa', 'phone' => '0911222333']);
        $other = $this->makeStudio('tim-khac');
        $this->seedOrders($other, 2, 'in_progress');

        $this->get(route('quick-search', ['q' => 'N00']))->assertRedirect(); // chưa đăng nhập
        $this->actingAs($studio->owner);

        $res = $this->getJson(route('quick-search', ['q' => $orders[1]->code]))->assertOk()->json();
        $this->assertSame($orders[1]->code, $res['orders'][0]['code']);
        $this->assertSame(route('orders.show', $orders[1]->id), $res['orders'][0]['url']);

        $res = $this->getJson(route('quick-search', ['q' => 'Đơn']))->json();
        $this->assertCount(3, $res['orders'], 'Không lẫn đơn studio khác.');
        $this->assertSame('Anh Khoa', $this->getJson(route('quick-search', ['q' => '0911222']))->json('clients.0.name'));
        $this->assertSame(['orders' => [], 'clients' => []], $this->getJson(route('quick-search', ['q' => '  ']))->json());
        $this->getJson(route('quick-search', ['q' => '%_']))->assertOk()->assertJsonCount(0, 'orders');
    }

    public function test_work_area_has_shortcuts_and_palette(): void
    {
        $studio = $this->makeStudio('phim-tat');
        [$o] = $this->seedOrders($studio, 1);
        $this->actingAs($studio->owner);

        $this->get(route('today'))->assertOk()
            ->assertSee('data-shortcuts=', false)->assertSee(route('orders.create'), false)
            ->assertSee('Bảng lệnh', false)->assertSee('Phím tắt')->assertSee('Tìm nhanh');
        $this->get(route('orders.show', $o->id))->assertSee('data-shortcut="edit"', false);
        $this->get(route('orders.index'))->assertSee('data-search-focus', false);

        // Trang khách không có phím tắt khu làm việc.
        auth()->logout();
        $this->get(route('public.order', 'phim-tat'))->assertDontSee('data-shortcuts=', false);
    }
}
