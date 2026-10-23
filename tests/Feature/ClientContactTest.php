<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Studio;
use App\Support\Facebook;

/**
 * Liên hệ của khách: không bắt buộc SĐT nhưng phải có 1 trong 3 (SĐT, email, Facebook);
 * Facebook là 1 cách nhận diện khách cũ như SĐT và email.
 */
class ClientContactTest extends NetTestCase
{
    private function order(Studio $studio, array $contact, array $extra = [])
    {
        return $this->post(route('public.order.store', $studio->slug), $this->guard('order-'.$studio->id) + [
            'title' => 'Logo quán cà phê',
            'brief' => 'Quán nhỏ, phong cách tối giản.',
            'name' => 'Minh Anh',
        ] + $contact + $extra);
    }

    // ================= Chuẩn hoá Facebook =================

    public function test_facebook_normalization(): void
    {
        $same = ['facebook.com/Minh.Anh', 'https://m.facebook.com/minh.anh?ref=bookmarks', 'fb.com/minh.anh/', '@Minh.Anh', 'minh.anh',
            'm.me/minh.anh', 'https://www.messenger.com/t/minh.anh', 'http://web.facebook.com/minh.anh#top'];
        foreach ($same as $input) {
            $this->assertSame('https://www.facebook.com/minh.anh', Facebook::normalize($input), $input);
        }

        $this->assertSame('https://www.facebook.com/profile.php?id=100012345678', Facebook::normalize('https://www.facebook.com/profile.php?id=100012345678&mibextid=x'));
        $this->assertSame('https://www.facebook.com/profile.php?id=100012345678', Facebook::normalize('facebook.com/people/Minh-Anh/100012345678/'));
        $this->assertSame('https://www.facebook.com/profile.php?id=100012345678', Facebook::normalize('100012345678'));

        // Tên Facebook / số điện thoại gõ nhầm ô / 1 chữ trơn: giữ nguyên dạng chữ, không tạo link.
        $this->assertSame('Nguyễn Minh Anh', Facebook::normalize('  Nguyễn   Minh Anh '));
        $this->assertFalse(Facebook::isLink(Facebook::normalize('0912345678')));
        $this->assertFalse(Facebook::isLink(Facebook::normalize('Hoang')));
        $this->assertNull(Facebook::normalize('   '));

        // Link không phải Facebook → báo lỗi.
        foreach (['https://instagram.com/minh', 'zalo.me/0912345678', 'facebook.com', 'https://facebook.com.evil.com/x'] as $foreign) {
            $this->assertTrue(Facebook::isForeignUrl($foreign), $foreign);
        }
        $this->assertFalse(Facebook::isForeignUrl('Nguyễn Minh Anh'));
        $this->assertFalse(Facebook::isForeignUrl('minh.anh'));

        $this->assertSame('https://m.me/minh.anh', Facebook::messengerUrl('https://www.facebook.com/minh.anh'));
        $this->assertNull(Facebook::messengerUrl('Nguyễn Minh Anh'));
    }

    // ================= Trang đặt đơn công khai =================

    public function test_public_order_needs_one_of_three_contacts_but_not_phone(): void
    {
        $studio = $this->makeStudio('mot-trong-ba');

        // Không có liên hệ nào → báo lỗi, không tạo đơn.
        $this->order($studio, [])->assertSessionHasErrors('phone');
        $this->order($studio, ['phone' => '', 'email' => '', 'facebook' => '   '])->assertSessionHasErrors('phone');
        $this->assertSame(0, Order::count());

        // Link không phải Facebook dán vào ô Facebook → lỗi riêng ở ô đó.
        $this->order($studio, ['facebook' => 'https://instagram.com/minhanh'])->assertSessionHasErrors('facebook');
        // SĐT có nhưng sai định dạng → vẫn báo lỗi định dạng.
        $this->order($studio, ['phone' => '12ab'])->assertSessionHasErrors('phone');
        $this->assertSame(0, Order::count());

        // Chỉ Facebook.
        $this->order($studio, ['facebook' => 'facebook.com/Minh.Anh'])->assertRedirect();
        $client = Order::firstOrFail()->client;
        $this->assertNull($client->phone);
        $this->assertSame('https://www.facebook.com/minh.anh', $client->facebook);

        // Chỉ email.
        $this->order($studio, ['email' => 'Khach@Example.com'], ['name' => 'Khách Email'])->assertRedirect();
        $this->assertSame('khach@example.com', Order::latest('id')->first()->client->email);

        // Chỉ SĐT.
        $this->order($studio, ['phone' => '0901 222 333'], ['name' => 'Khách SĐT'])->assertRedirect();
        $this->assertSame('0901222333', Order::latest('id')->first()->client->phone);

        $this->assertSame(3, Client::count());
        $this->get(route('public.order', $studio->slug))->assertOk()->assertSee('Facebook')->assertSee('ít nhất 1');
    }

    public function test_returning_client_recognised_by_facebook_or_email(): void
    {
        $studio = $this->makeStudio('nhan-dien');

        $this->order($studio, ['facebook' => 'https://www.facebook.com/minh.anh'])->assertRedirect();
        $first = Order::latest('id')->first();

        // Cùng Facebook, gõ kiểu khác + thêm SĐT → cùng khách, SĐT còn trống được bổ sung.
        $this->order($studio, ['facebook' => '@Minh.Anh', 'phone' => '0903 000 111'], ['name' => 'Anh Minh'])->assertRedirect();
        $second = Order::latest('id')->first();
        $this->assertSame($first->client_id, $second->client_id);
        $client = $second->client->fresh();
        $this->assertSame('0903000111', $client->phone);
        // Không ghi đè tên đã lưu — nhưng ghi chú nội bộ cho freelancer biết.
        $this->assertSame('Minh Anh', $client->name);
        $this->assertTrue($second->events()->where('visible', false)->where('message', 'like', '%Anh Minh%')->exists());

        // Giờ khách dùng SĐT đó → vẫn nhận ra.
        $this->order($studio, ['phone' => '0903000111'])->assertRedirect();
        $this->assertSame($first->client_id, Order::latest('id')->first()->client_id);

        // Email không phân biệt hoa thường.
        $this->order($studio, ['email' => 'lan@example.com'], ['name' => 'Lan'])->assertRedirect();
        $lan = Order::latest('id')->first()->client_id;
        $this->order($studio, ['email' => 'LAN@example.com'], ['name' => 'Lan'])->assertRedirect();
        $this->assertSame($lan, Order::latest('id')->first()->client_id);

        // Tên Facebook dạng chữ KHÔNG dùng để ghép (dễ trùng tên).
        $this->order($studio, ['facebook' => 'Nguyễn Văn A'], ['name' => 'A1'])->assertRedirect();
        $this->order($studio, ['facebook' => 'Nguyễn Văn A'], ['name' => 'A2'])->assertRedirect();
        $this->assertSame(2, Client::where('facebook', 'Nguyễn Văn A')->count());

        // Khách của studio khác không bị ghép nhầm.
        $other = $this->makeStudio('studio-khac');
        $this->order($other, ['facebook' => 'facebook.com/minh.anh'])->assertRedirect();
        $this->assertSame($other->id, Order::latest('id')->first()->client->studio_id);
        $this->assertNotSame($first->client_id, Order::latest('id')->first()->client_id);
    }

    public function test_public_form_cannot_overwrite_saved_contacts(): void
    {
        $studio = $this->makeStudio('khong-ghi-de');
        $client = $studio->clients()->create(['name' => 'Chị Hoa', 'phone' => '0904000111', 'email' => 'hoa@example.com']);

        $this->order($studio, ['phone' => '0904 000 111', 'email' => 'ke-gian@example.com', 'facebook' => 'facebook.com/hoa.that'], ['name' => 'Kẻ gian'])->assertRedirect();

        $client->refresh();
        $this->assertSame('Chị Hoa', $client->name);
        $this->assertSame('hoa@example.com', $client->email);
        $this->assertSame('https://www.facebook.com/hoa.that', $client->facebook); // còn trống → được bổ sung
    }

    // ================= Trang Khách hàng =================

    public function test_clients_page_facebook_field_and_one_of_three_rule(): void
    {
        $studio = $this->makeStudio('trang-khach');
        $this->actingAs($studio->owner);

        $this->get(route('clients.index'))->assertOk()->assertSee('Facebook');

        $this->post(route('clients.store'), ['name' => 'Không liên hệ'])->assertSessionHasErrors('phone');
        $this->post(route('clients.store'), ['name' => 'Sai link', 'facebook' => 'https://tiktok.com/@x'])->assertSessionHasErrors('facebook');
        $this->assertSame(0, Client::count());

        $this->post(route('clients.store'), ['name' => 'Bảo Ngọc', 'facebook' => 'https://www.facebook.com/bao.ngoc.design'])->assertRedirect();
        $client = Client::firstOrFail();
        $this->assertSame('https://www.facebook.com/bao.ngoc.design', $client->facebook);
        $this->get(route('clients.show', $client->id))->assertOk()
            ->assertSee('bao.ngoc.design')
            ->assertSee('https://m.me/bao.ngoc.design', false);

        // Thêm trùng Facebook → mở hồ sơ có sẵn, không tạo trùng.
        $this->post(route('clients.store'), ['name' => 'Ngọc', 'facebook' => 'fb.com/Bao.Ngoc.Design'])
            ->assertRedirect(route('clients.show', $client->id))->assertSessionHas('warning');
        $this->assertSame(1, Client::count());

        // Tìm theo Facebook.
        $this->get(route('clients.index', ['q' => 'bao.ngoc']))->assertOk()->assertSee('Bảo Ngọc');

        // Sửa: bỏ hết liên hệ → lỗi; đổi sang chỉ SĐT → được.
        $this->put(route('clients.update', $client->id), ['name' => 'Bảo Ngọc'])->assertSessionHasErrors('phone');
        $this->put(route('clients.update', $client->id), ['name' => 'Bảo Ngọc', 'phone' => '0905 111 222'])->assertRedirect();
        $client->refresh();
        $this->assertSame('0905111222', $client->phone);
        $this->assertNull($client->facebook);

        // Danh sách hiện liên hệ phù hợp khi không có SĐT.
        $studio->clients()->create(['name' => 'Chỉ FB', 'facebook' => 'https://www.facebook.com/chi.fb']);
        $this->get(route('clients.index'))->assertSee('Facebook: chi.fb');
    }

    // ================= Tạo đơn thủ công =================

    public function test_manual_order_new_client_with_facebook_only_and_dedupe(): void
    {
        $studio = $this->makeStudio('don-tay');
        $this->actingAs($studio->owner);
        $base = ['title' => 'Bộ nhận diện', 'priority' => 'normal', 'brief' => 'Logo + namecard', 'revision_rounds' => 2, 'deposit_percent' => 0];

        // Khách mới không có liên hệ nào → lỗi.
        $this->post(route('orders.store'), $base + ['client_name' => 'Tuấn'])->assertSessionHasErrors('client_phone');

        $this->post(route('orders.store'), $base + ['client_name' => 'Tuấn', 'client_facebook' => 'facebook.com/tuan.coffee'])->assertRedirect();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('https://www.facebook.com/tuan.coffee', $order->client->facebook);
        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('https://m.me/tuan.coffee', false);

        // "Khách mới" nhưng trùng Facebook → gắn vào khách cũ.
        $this->post(route('orders.store'), $base + ['client_name' => 'Anh Tuấn', 'client_facebook' => '@tuan.coffee', 'client_phone' => '0906 777 888'])
            ->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'khách có sẵn'));
        $this->assertSame(1, Client::count());
        $this->assertSame('0906777888', Client::first()->phone);

        // Chọn khách có sẵn: không cần nhập liên hệ.
        $this->post(route('orders.store'), $base + ['client_id' => Client::first()->id])->assertRedirect();
        $this->assertSame(3, Order::count());

        // Không chọn khách, không nhập tên → chỉ 1 lỗi về tên.
        $this->post(route('orders.store'), $base)->assertSessionHasErrors('client_name')->assertSessionDoesntHaveErrors('client_phone');
    }

    // ================= Migration chuyển dữ liệu cũ =================

    public function test_migration_moves_facebook_links_out_of_contact_url_and_is_rerunnable(): void
    {
        $studio = $this->makeStudio('du-lieu-cu');
        $old = $studio->clients()->create(['name' => 'Cũ', 'phone' => '0907000111', 'contact_url' => 'https://m.facebook.com/khach.cu']);
        $insta = $studio->clients()->create(['name' => 'Insta', 'phone' => '0907000222', 'contact_url' => 'https://instagram.com/insta']);

        (require database_path('migrations/2026_10_12_000002_add_facebook_to_clients.php'))->up();
        (require database_path('migrations/2026_10_12_000001_ensure_google_columns_on_users.php'))->up();

        $this->assertSame('https://www.facebook.com/khach.cu', $old->fresh()->facebook);
        $this->assertNull($old->fresh()->contact_url);
        $this->assertNull($insta->fresh()->facebook);
        $this->assertSame('https://instagram.com/insta', $insta->fresh()->contact_url);
    }

    public function test_csv_export_includes_email_and_facebook(): void
    {
        $studio = $this->makeStudio('xuat-csv');
        $this->order($studio, ['facebook' => 'facebook.com/csv.khach', 'email' => 'csv@example.com'])->assertRedirect();

        $csv = $this->actingAs($studio->owner)->get(route('reports.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Facebook', $csv);
        $this->assertStringContainsString('https://www.facebook.com/csv.khach', $csv);
        $this->assertStringContainsString('csv@example.com', $csv);
    }
}
