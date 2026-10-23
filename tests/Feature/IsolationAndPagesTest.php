<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\UploadedFile;

/** Mỗi studio chỉ thấy dữ liệu của mình; mọi trang mở được không lỗi. */
class IsolationAndPagesTest extends NetTestCase
{
    private function seedOrder($studio): Order
    {
        $client = $studio->clients()->create(['name' => 'Khách A', 'phone' => '0900000001']);
        $order = app(OrderService::class)->create($studio, $client, ['title' => 'Đơn của '.$studio->slug, 'brief' => 'Brief'], 'manual', $studio->services()->first(), [['url' => 'https://drive.google.com/x']]);

        return $order->fresh();
    }

    public function test_other_studio_cannot_touch_my_data(): void
    {
        $a = $this->makeStudio('studio-a');
        $b = $this->makeStudio('studio-b');
        $order = $this->seedOrder($a);

        $this->actingAs($a->owner);
        $this->post(route('rounds.store', $order->id));
        $round = $order->rounds()->first();
        $this->post(route('proposals.store', $round->id), ['label' => 'A', 'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)]]);
        $proposal = $round->proposals()->first();
        $image = $proposal->images()->first();
        $link = $order->links()->first();
        $client = $order->client;
        $service = $a->services()->first();

        $this->actingAs($b->owner);
        $this->get(route('orders.show', $order->id))->assertNotFound();
        $this->get(route('orders.edit', $order->id))->assertNotFound();
        $this->put(route('orders.update', $order->id), ['title' => 'hack'])->assertNotFound();
        $this->delete(route('orders.destroy', $order->id))->assertNotFound();
        $this->patchJson(route('orders.status', $order->id), ['status' => 'cancelled'])->assertNotFound();
        $this->put(route('orders.charges', $order->id), ['deposit_percent' => 0])->assertNotFound();
        $this->post(route('orders.pay', $order->id), ['amount' => 1])->assertNotFound();
        $this->post(route('orders.token', $order->id))->assertNotFound();
        $this->post(route('rounds.send', $round->id))->assertNotFound();
        $this->delete(route('rounds.destroy', $round->id))->assertNotFound();
        $this->post(route('proposals.store', $round->id), ['label' => 'x'])->assertNotFound();
        $this->put(route('proposals.update', $proposal->id), ['label' => 'x'])->assertNotFound();
        $this->delete(route('proposals.destroy', $proposal->id))->assertNotFound();
        $this->delete(route('images.destroy', $image->id))->assertNotFound();
        $this->delete(route('links.destroy', $link->id))->assertNotFound();
        $this->get(route('clients.show', $client->id))->assertNotFound();
        $this->put(route('services.update', $service->id), ['name' => 'x'])->assertNotFound();

        // Không gắn được khách / dịch vụ của studio khác vào đơn của mình.
        $this->post(route('orders.store'), ['client_id' => $client->id, 'title' => 'x', 'brief' => 'y', 'priority' => 'normal', 'revision_rounds' => 1, 'deposit_percent' => 0])
            ->assertSessionHasErrors('client_id');
        $this->post(route('orders.store'), ['client_name' => 'Mới', 'service_id' => $service->id, 'title' => 'x', 'brief' => 'y', 'priority' => 'normal', 'revision_rounds' => 1, 'deposit_percent' => 0])
            ->assertSessionHasErrors('service_id');

        $this->get(route('orders.index'))->assertOk()->assertDontSee('Đơn của studio-a');
        $this->assertSame('Đơn của studio-a', $order->fresh()->title);
        $this->assertNotNull($image->fresh());
    }

    public function test_every_studio_page_renders(): void
    {
        $studio = $this->makeStudio('trang', ['bank_code' => 'MB', 'bank_account' => '123456']);
        $order = $this->seedOrder($studio);
        $this->actingAs($studio->owner);
        $this->post(route('rounds.store', $order->id));

        foreach ([
            route('today'), route('orders.index'), route('orders.index', ['loc' => 'overdue']), route('orders.index', ['loc' => 'unpaid', 'q' => 'Khách']),
            route('orders.board'), route('orders.calendar'), route('orders.calendar', ['thang' => '2026-02']), route('orders.create'),
            route('orders.show', $order->id), route('orders.edit', $order->id),
            route('clients.index'), route('clients.show', $order->client_id), route('services.index'),
            route('reports.index'), route('reports.index', ['thang' => '2025-12']), route('settings.index'), route('profile.edit'),
            route('public.order', 'trang'), route('public.track', $order->tracking_token),
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('settings.export'))->assertOk();
    }

    public function test_public_and_admin_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('một đường link');
        foreach (['/gioi-thieu', '/dieu-khoan-su-dung', '/chinh-sach-bao-mat', '/login', '/dang-ky', '/quen-mat-khau', '/robots.txt', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk();
        }
        $studio = $this->makeStudio('sitemap-me');
        $this->get('/sitemap.xml')->assertSee('/dat/sitemap-me');
        $this->get('/dat/khong-ton-tai')->assertNotFound();

        $admin = $this->admin();
        $this->actingAs($admin);
        // Super Admin cũng là chủ studio: vào thẳng khu làm việc (studio riêng được tạo tự động), không bị ép về khu quản trị.
        $this->get(route('today'))->assertOk();
        $this->assertNotNull($admin->fresh()->studio);
        foreach ([route('admin.dashboard'), route('admin.dashboard', ['q' => 'sitemap']), route('admin.studios.show', $studio->id), route('admin.users.index'), route('admin.settings.edit'), route('admin.pages.edit', 'gioi-thieu'), route('profile.edit')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->put(route('admin.studios.toggle', $studio->id))->assertRedirect();
        $this->assertFalse($studio->fresh()->is_active);

        // Studio bị khoá: chủ không vào được khu làm việc.
        $this->actingAs($studio->owner);
        $this->get(route('today'))->assertForbidden();
    }

    public function test_owner_cannot_open_admin_area(): void
    {
        $studio = $this->makeStudio('thuong');
        $this->actingAs($studio->owner)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_settings_update_and_account_deletion(): void
    {
        $studio = $this->makeStudio('cai-dat');
        $order = $this->seedOrder($studio);
        $this->actingAs($studio->owner);

        $this->put(route('settings.profile'), ['name' => 'Studio Mới', 'slug' => 'studio-moi', 'phone' => '0912 111 222'])->assertRedirect();
        $this->assertSame('studio-moi', $studio->fresh()->slug);
        $this->put(route('settings.profile'), ['name' => 'X', 'slug' => 'login'])->assertSessionHasErrors('slug');
        $this->put(route('settings.payment'), ['bank_code' => 'vcb', 'bank_account' => '0123 456', 'bank_holder' => 'Nguyễn Linh'])->assertRedirect();
        $this->assertSame('NGUYEN LINH', $studio->fresh()->bank_holder);
        $this->put(route('settings.appearance'), ['accent_color' => 'cyan'])->assertRedirect();
        $this->put(route('settings.appearance'), ['accent_color' => 'red'])->assertSessionHasErrors('accent_color');
        $this->post(route('settings.avatar'), ['avatar' => UploadedFile::fake()->image('me.png', 900, 600)])->assertRedirect();
        $this->assertNotNull($studio->fresh()->avatar_path);
        $this->put(route('settings.order-form'), ['default_deposit_percent' => 30, 'default_revision_rounds' => 3, 'policy' => 'Quy định'])->assertRedirect();
        $this->assertFalse($studio->fresh()->order_form_enabled);

        $this->delete(route('profile.destroy'), ['delete_password' => 'password123', 'confirmation' => 'xoa'])->assertSessionHasErrors('confirmation');
        $this->delete(route('profile.destroy'), ['delete_password' => 'password123', 'confirmation' => 'XOA TAI KHOAN'])->assertRedirect(route('login'));
        $this->assertNull($studio->fresh());
        $this->assertNull(Order::find($order->id));
    }
}
