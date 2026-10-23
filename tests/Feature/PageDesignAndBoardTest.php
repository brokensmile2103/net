<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PortfolioItem;
use App\Support\Color;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Mẫu giao diện trang đặt đơn, màu riêng, ảnh bìa, tác phẩm tiêu biểu; dữ liệu cho Bảng việc kéo thả. */
class PageDesignAndBoardTest extends NetTestCase
{
    public function test_color_palette_keeps_text_readable(): void
    {
        foreach (['#f5e663', '#7fe0c4', '#c8106e', '#0a7fb8', '#000000'] as $hex) {
            $p = Color::palette($hex);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['--accent-ink'], '#ffffff'), $hex.' chữ nhấn trên nền trắng');
            $this->assertGreaterThanOrEqual(3, Color::contrast($hex, $p['--on-accent']), $hex.' chữ trên nút');
        }
        $this->assertSame('#1d1d22', Color::palette('#f5e663')['--on-accent']); // nền vàng → chữ mực
        $this->assertSame('#ffffff', Color::palette('#c8106e')['--on-accent']);
        $this->assertSame('#aabbcc', Color::normalize('ABC'));
        $this->assertNull(Color::normalize('#12345g'));
        $this->assertSame('#0078bf', Color::palette('#f5e663')['--riso-face']); // màu nhạt → đảo lớp mực ở mẫu Riso
    }

    public function test_owner_picks_theme_custom_accent_and_headline(): void
    {
        $studio = $this->makeStudio('chon-mau');
        $this->actingAs($studio->owner);
        $this->get(route('settings.index'))->assertOk()->assertSee('Trang đặt đơn')->assertSee('Bàn vẽ')->assertSee('Sổ phác thảo');

        $this->put(route('settings.appearance'), ['page_theme' => 'galaxy', 'accent_color' => 'cyan'])->assertSessionHasErrors('page_theme');
        $this->put(route('settings.appearance'), ['page_theme' => 'riso', 'accent_color' => 'custom'])->assertSessionHasErrors('accent_custom');
        $this->put(route('settings.appearance'), ['page_theme' => 'riso', 'accent_color' => 'custom', 'accent_custom' => 'đỏ'])->assertSessionHasErrors('accent_custom');
        $this->put(route('settings.appearance'), ['page_theme' => 'riso', 'accent_color' => 'custom', 'accent_custom' => '#fefefe'])->assertSessionHasErrors('accent_custom');

        $this->put(route('settings.appearance'), ['page_theme' => 'riso', 'accent_color' => 'custom', 'accent_custom' => 'FF5A1F', 'page_headline' => 'Vẽ cho thương hiệu nhỏ'])->assertRedirect();
        $studio->refresh();
        $this->assertSame('riso', $studio->pageTheme());
        $this->assertSame('#ff5a1f', $studio->accentHex());
        $this->assertSame('Vẽ cho thương hiệu nhỏ', $studio->page_headline);

        // Trang khách: đúng mẫu, đúng bộ màu, câu chào.
        auth()->logout();
        $page = $this->get(route('public.order', 'chon-mau'))->assertOk()
            ->assertSee('data-theme="riso"', false)
            ->assertSee('--accent:#ff5a1f', false)
            ->assertSee('Vẽ cho thương hiệu nhỏ')
            ->assertSee('id="dat-don"', false);
        $page->assertDontSee('Đang xem thử');

        // Quay về màu có sẵn: vẫn nhớ mã màu riêng; khu làm việc dùng màu có sẵn.
        $this->actingAs($studio->owner)->put(route('settings.appearance'), ['page_theme' => 'swiss', 'accent_color' => 'green'])->assertRedirect();
        $studio->refresh();
        $this->assertSame('#14804a', $studio->accentHex());
        $this->assertSame('#ff5a1f', $studio->accent_custom);
        $this->assertSame('Vẽ cho thương hiệu nhỏ', $studio->page_headline, 'Không gửi ô câu chào thì giữ nguyên.');
        $this->get(route('today'))->assertOk()->assertSee('data-accent="green"', false);
    }

    public function test_every_theme_renders_order_and_tracking_pages(): void
    {
        $studio = $this->makeStudio('du-mau', ['tagline' => 'Logo cho quán nhỏ']);
        $client = $studio->clients()->create(['name' => 'K', 'phone' => '0900000111']);
        $order = app(\App\Services\OrderService::class)->create($studio, $client, ['title' => 'Logo quán', 'brief' => 'B'], 'manual');

        $this->actingAs($studio->owner);
        $this->post(route('settings.page-cover'), ['cover' => UploadedFile::fake()->image('bia.jpg', 2400, 1200)])->assertRedirect();
        $this->post(route('portfolio.store'), ['works' => [UploadedFile::fake()->image('a.jpg', 1200, 900), UploadedFile::fake()->image('b.png', 800, 1000)]])->assertRedirect();
        auth()->logout();

        foreach (array_keys(config('net.page_themes')) as $theme) {
            $studio->forceFill(['page_theme' => $theme])->save();
            $this->get(route('public.order', 'du-mau'))->assertOk()
                ->assertSee('data-theme="'.$theme.'"', false)
                ->assertSee('Tác phẩm tiêu biểu')
                ->assertSee($studio->fresh()->coverUrl(), false)
                ->assertSee('Gửi yêu cầu');
            $this->get(route('public.track', $order->tracking_token))->assertOk()->assertSee('data-theme="'.$theme.'"', false)->assertSee('Logo quán');
        }
    }

    public function test_theme_preview_only_for_the_owner(): void
    {
        $studio = $this->makeStudio('xem-thu', ['page_theme' => 'paper']);

        $this->get(route('public.order', ['slug' => 'xem-thu', 'xem-thu' => 'artboard']))->assertOk()
            ->assertSee('data-theme="paper"', false)->assertDontSee('Đang xem thử');

        $this->actingAs($this->makeStudio('nguoi-la')->owner)
            ->get(route('public.order', ['slug' => 'xem-thu', 'xem-thu' => 'artboard']))->assertSee('data-theme="paper"', false);

        $this->actingAs($studio->owner)
            ->get(route('public.order', ['slug' => 'xem-thu', 'xem-thu' => 'artboard']))->assertOk()
            ->assertSee('data-theme="artboard"', false)->assertSee('Đang xem thử')->assertSee('pf-ruler', false);
        $this->get(route('public.order', ['slug' => 'xem-thu', 'xem-thu' => '<script>']))->assertSee('data-theme="paper"', false);
        $this->assertSame('paper', $studio->fresh()->page_theme, 'Xem thử không lưu.');
    }

    public function test_portfolio_manage_order_caption_quota_and_isolation(): void
    {
        $studio = $this->makeStudio('tac-pham');
        $this->actingAs($studio->owner);

        $this->post(route('portfolio.store'), ['works' => [
            UploadedFile::fake()->image('1.jpg', 1000, 800), UploadedFile::fake()->image('2.jpg', 1000, 800), UploadedFile::fake()->image('3.jpg', 1000, 800),
        ]])->assertRedirect();
        $items = $studio->portfolioItems()->get();
        $this->assertCount(3, $items);
        $this->assertGreaterThan(0, $studio->fresh()->storage_bytes);
        foreach ($items as $it) {
            Storage::disk('public')->assertExists($it->path);
            $this->assertLessThanOrEqual(1600, max($it->width, $it->height));
        }

        // Chú thích + đưa ảnh thứ 3 lên trước.
        $this->put(route('portfolio.update', $items[2]->id), ['caption' => 'Bao bì trà'])->assertRedirect();
        $this->post(route('portfolio.up', $items[2]->id))->assertRedirect();
        $this->assertSame([$items[0]->id, $items[2]->id, $items[1]->id], $studio->portfolioItems()->pluck('id')->all());
        $this->get(route('settings.index'))->assertSee('Bao bì trà');

        // Giới hạn số tác phẩm.
        $max = config('net.portfolio.max_items');
        $files = array_map(fn ($i) => UploadedFile::fake()->image($i.'.jpg', 400, 300), range(1, $max));
        $this->post(route('portfolio.store'), ['works' => $files])->assertSessionHasErrors('works');
        $this->assertCount(3, $studio->portfolioItems()->get());
        $this->post(route('portfolio.store'), ['works' => [UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('works.0');

        // Studio khác không đụng được.
        $this->actingAs($this->makeStudio('khac-nua')->owner);
        $this->delete(route('portfolio.destroy', $items[0]->id))->assertNotFound();
        $this->put(route('portfolio.update', $items[0]->id), ['caption' => 'x'])->assertNotFound();

        // Xoá → trả dung lượng, xoá file.
        $this->actingAs($studio->owner);
        $bytes = $studio->fresh()->storage_bytes;
        $this->delete(route('portfolio.destroy', $items[0]->id))->assertRedirect();
        Storage::disk('public')->assertMissing($items[0]->path);
        $this->assertLessThan($bytes, $studio->fresh()->storage_bytes);
        $this->assertNull(PortfolioItem::find($items[0]->id));

        // Ảnh bìa: tải lên, thay, gỡ.
        $this->post(route('settings.page-cover'), ['cover' => UploadedFile::fake()->image('c1.jpg', 2000, 1000)])->assertRedirect();
        $first = $studio->fresh()->cover_path;
        $this->post(route('settings.page-cover'), ['cover' => UploadedFile::fake()->image('c2.jpg', 2000, 1000)])->assertRedirect();
        Storage::disk('public')->assertMissing($first);
        $this->post(route('settings.page-cover'), ['remove' => 1])->assertRedirect();
        $this->assertNull($studio->fresh()->cover_path);
    }

    public function test_board_exposes_drag_data_and_status_endpoint_rules(): void
    {
        $studio = $this->makeStudio('bang-viec');
        $client = $studio->clients()->create(['name' => 'K', 'phone' => '0900000222']);
        $svc = app(\App\Services\OrderService::class);
        $a = $svc->create($studio, $client, ['title' => 'Đơn A', 'brief' => 'B'], 'manual', null, [], 'in_progress');
        $b = $svc->create($studio, $client, ['title' => 'Đơn B', 'brief' => 'B'], 'manual', null, [], 'approved');
        $b->links()->create(['kind' => 'source', 'url' => 'https://drive.google.com/x']);
        $svc->setCharges($a, [['kind' => 'base', 'label' => 'Logo', 'amount' => 1500000]]);

        $this->actingAs($studio->owner);
        $this->get(route('orders.board'))->assertOk()
            ->assertSee('data-id="'.$a->id.'"', false)
            ->assertSee('data-can-deliver="0"', false)
            ->assertSee('data-can-deliver="1"', false)
            ->assertSee('data-amount="1500000"', false)
            ->assertSee('1,5tr')
            ->assertSee('Hoàn tác');

        // Các thao tác trên bảng dùng chung endpoint JSON.
        $this->patchJson(route('orders.status', $a->id), ['status' => 'delivered'])->assertStatus(422);
        $this->patchJson(route('orders.status', $b->id), ['status' => 'delivered'])->assertOk();
        $this->patchJson(route('orders.status', $b->id), ['status' => 'approved'])->assertOk(); // Hoàn tác
        $this->assertSame('approved', $b->fresh()->status);
        $this->patchJson(route('orders.status', $a->id), ['status' => 'cancelled', 'reason' => 'Khách đổi ý'])->assertOk();
        $this->assertSame('cancelled', $a->fresh()->status);
        $this->get(route('orders.board'))->assertDontSee('data-id="'.$a->id.'"', false);

        $other = $this->makeStudio('bang-khac');
        $this->actingAs($other->owner)->patchJson(route('orders.status', $b->id), ['status' => 'review'])->assertNotFound();
    }
}
