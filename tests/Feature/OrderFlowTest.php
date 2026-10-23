<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Round;
use App\Models\Studio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Luồng đầy đủ: khách đặt đơn → báo giá → cọc → phương án → góp ý có ghim → duyệt → bàn giao có khoá. */
class OrderFlowTest extends NetTestCase
{
    private function placeOrder(Studio $studio, array $extra = []): Order
    {
        $service = $studio->services()->first();

        $this->post(route('public.order.store', $studio->slug), $this->guard('order-'.$studio->id) + [
            'service_id' => $service->id,
            'title' => 'Logo tiệm bánh Mây',
            'brief' => 'Tiệm bánh nhỏ ở Đà Lạt, khách là sinh viên. Muốn ấm áp, dễ thương.',
            'colors' => 'Cam đất, kem',
            'links' => ['https://drive.google.com/drive/folders/abc123', ''],
            'name' => 'Chị Thảo',
            'phone' => '0901 234 567',
            'email' => 'thao@example.com',
        ] + $extra)->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_register_creates_studio_with_sample_services(): void
    {
        $this->post(route('register.store'), $this->guard('register') + [
            'studio_name' => 'Nhà Nét Nhỏ',
            'slug' => 'nha-net-nho',
            'name' => 'Nguyễn Linh',
            'phone' => '0912 345 678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('today'));

        $studio = Studio::where('slug', 'nha-net-nho')->firstOrFail();
        $this->assertSame('0912345678', $studio->owner->phone);
        $this->assertGreaterThan(0, $studio->services()->count());
        $this->get(route('today'))->assertOk()->assertSee('Bắt đầu với Nét');
    }

    public function test_reserved_or_duplicate_slug_is_rejected(): void
    {
        $this->makeStudio('da-co');

        foreach (['da-co', 'admin', 'A B'] as $slug) {
            $this->post(route('register.store'), $this->guard('register') + [
                'studio_name' => 'X', 'slug' => $slug, 'name' => 'Y', 'phone' => '0933'.random_int(100000, 999999),
                'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1',
            ])->assertSessionHasErrors('slug');
        }
    }

    public function test_full_order_flow(): void
    {
        $studio = $this->makeStudio('linh', ['bank_code' => 'VCB', 'bank_account' => '0123456789', 'bank_holder' => 'NGUYEN LINH', 'lock_delivery_until_paid' => true]);
        $owner = $studio->owner;

        // Trang đặt đơn công khai.
        $this->get(route('public.order', 'linh'))->assertOk()->assertSee('Đặt thiết kế')->assertSee($studio->services()->first()->name);

        $order = $this->placeOrder($studio);
        $this->assertSame('new', $order->status);
        $this->assertSame('N0001', $order->code);
        $this->assertSame('0901234567', $order->client->phone);
        $this->assertSame(1, $order->links()->where('kind', 'reference')->count());
        $this->assertSame(40, strlen($order->tracking_token));

        // Khách được chuyển sang trang theo dõi.
        $this->get(route('public.track', $order->tracking_token))->assertOk()->assertSee('Logo tiệm bánh Mây')->assertSee('đang xem yêu cầu');

        // Đặt lần 2 cùng SĐT → cùng 1 khách.
        $second = $this->placeOrder($studio, ['title' => 'Bao bì hộp bánh']);
        $this->assertSame($order->client_id, $second->client_id);
        $this->assertSame('N0002', $second->code);

        // Freelancer báo giá & gửi.
        $this->actingAs($owner);
        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('N0001');
        $this->put(route('orders.charges', $order->id), [
            'charges' => [
                ['kind' => 'base', 'label' => 'Thiết kế logo', 'amount' => '2500000'],
                ['kind' => 'rush', 'label' => '', 'amount' => '500000'],
                ['kind' => 'discount', 'label' => 'Khách quen', 'amount' => '200000'],
            ],
            'deposit_percent' => 50,
            'send' => 1,
        ])->assertRedirect();
        $order->refresh();
        $this->assertEquals(2800000, (float) $order->total_amount);
        $this->assertSame('quoted', $order->status);
        $this->assertEquals(1400000, $order->depositAmount());

        // Khách đồng ý báo giá, thấy mã QR đúng số tiền cọc.
        auth()->logout();
        $this->post(route('public.track.accept', $order->tracking_token))->assertRedirect();
        $this->assertNotNull($order->fresh()->quote_accepted_at);
        $this->post(route('public.track.accept', $order->tracking_token))->assertSessionHasErrors('quote');
        $this->get(route('public.track', $order->tracking_token))->assertOk()->assertSee('amount=1400000', false)->assertSee('NET N0001');

        // Ghi nhận cọc. Không cho thu quá số còn lại.
        $this->actingAs($owner);
        $this->post(route('orders.pay', $order->id), ['amount' => '1400000', 'paid_on' => now()->toDateString(), 'method' => 'chuyen_khoan'])->assertRedirect();
        $this->post(route('orders.pay', $order->id), ['amount' => '9000000', 'paid_on' => now()->toDateString(), 'method' => 'chuyen_khoan'])->assertSessionHasErrors('amount');
        $this->assertEquals(1400000, (float) $order->fresh()->paid_amount);
        $this->assertTrue($order->fresh()->depositPaid());

        // Vòng V1: 2 phương án có ảnh. Ảnh được nén sang WebP và giới hạn kích thước.
        $this->post(route('rounds.store', $order->id))->assertRedirect();
        $round = $order->rounds()->firstOrFail();
        $this->assertTrue($round->isDraft());

        $this->post(route('proposals.store', $round->id), [
            'label' => 'Phương án A', 'note' => 'Đám mây tròn',
            'images' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('a2.png', 800, 800)],
        ])->assertRedirect();
        $this->post(route('proposals.store', $round->id), [
            'label' => '', 'drive_url' => 'https://drive.google.com/file/d/xyz',
            'images' => [UploadedFile::fake()->image('b.jpg', 1200, 1600)],
        ])->assertRedirect();

        $round->refresh()->load('proposals.images');
        $this->assertCount(2, $round->proposals);
        $this->assertSame('Phương án B', $round->proposals[1]->label);
        $big = $round->proposals[0]->images[0];
        $this->assertLessThanOrEqual(config('net.images.preview_max_px'), max($big->width, $big->height));
        Storage::disk('public')->assertExists($big->path);
        $this->assertGreaterThan(0, $studio->fresh()->storage_bytes);

        // Vòng nháp: khách không thấy.
        auth()->logout();
        $this->get(route('public.track', $order->tracking_token))->assertOk()->assertDontSee('Phương án A');
        $this->post(route('public.track.respond', $order->tracking_token), ['decision' => 'approve'])->assertSessionHasErrors('round');

        // Gửi V1.
        $this->actingAs($owner);
        $this->post(route('rounds.send', $round->id), ['note' => 'Bạn xem giúp mình nhé'])->assertRedirect();
        $this->assertSame('review', $order->fresh()->status);

        auth()->logout();
        $this->get(route('public.track', $order->tracking_token))->assertOk()->assertSee('Phương án A')->assertSee('Phương án B')->assertSee('Chờ bạn phản hồi');

        // Có 2 phương án → phải chọn trước khi duyệt.
        $this->post(route('public.track.respond', $order->tracking_token), ['decision' => 'approve'])->assertSessionHasErrors('decision');
        // Góp ý trống → lỗi.
        $this->post(route('public.track.respond', $order->tracking_token), ['decision' => 'changes', 'note' => ''])->assertSessionHasErrors('note');

        // Góp ý có ghim (1 ghim hợp lệ, 1 ghim trên ảnh của đơn khác → bỏ qua).
        $imageA = $round->proposals[0]->images[0];
        $imageB = $round->proposals[1]->images[0];
        $this->post(route('public.track.respond', $order->tracking_token), [
            'decision' => 'changes',
            'note' => 'Tổng thể ổn, chỉnh vài chỗ',
            'pins' => [
                ['image_id' => $imageA->id, 'x' => 30.5, 'y' => 42.25, 'body' => 'Mây tròn quá'],
                ['image_id' => 999999, 'x' => 10, 'y' => 10, 'body' => 'Ghim lạ'],
                ['image_id' => $imageA->id, 'x' => 50, 'y' => 50, 'body' => '   '],
                ['image_id' => $imageB->id, 'x' => 70, 'y' => 20, 'body' => 'Chữ B đậm hơn'],
            ],
        ])->assertRedirect();

        $round->refresh();
        $order->refresh();
        $this->assertSame('changes', $round->status);
        $this->assertSame('revising', $order->status);
        $this->assertSame(1, $order->revisionsUsed());
        $pins = $round->feedback()->whereNotNull('image_id')->orderBy('id')->get();
        $this->assertCount(2, $pins);
        $this->assertEquals(30.5, $pins[0]->pin_x);
        // Mỗi ghim gắn đúng phương án chứa ảnh đó.
        $this->assertSame($round->proposals[0]->id, $pins[0]->proposal_id);
        $this->assertSame($round->proposals[1]->id, $pins[1]->proposal_id);

        // Không phản hồi lại được vòng đã đóng.
        $this->post(route('public.track.respond', $order->tracking_token), ['decision' => 'approve'])->assertSessionHasErrors('round');

        // Freelancer thấy ghim, đánh dấu đã sửa.
        $this->actingAs($owner);
        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('Mây tròn quá');
        $this->post(route('feedback.resolve', $pins[0]->id))->assertRedirect();
        $this->assertNotNull($pins[0]->fresh()->resolved_at);

        // Không xoá được ảnh của vòng đã khách phản hồi.
        $this->delete(route('images.destroy', $imageA->id))->assertStatus(422);

        // V2 với 1 phương án → khách duyệt thẳng.
        $this->post(route('rounds.store', $order->id))->assertRedirect();
        $round2 = $order->rounds()->where('number', 2)->firstOrFail();
        $this->post(route('proposals.store', $round2->id), ['label' => 'A sửa', 'images' => [UploadedFile::fake()->image('a3.jpg', 1000, 1000)]]);
        $this->post(route('rounds.send', $round2->id))->assertRedirect();

        auth()->logout();
        $this->post(route('public.track.respond', $order->tracking_token), ['decision' => 'approve', 'note' => 'Quá đẹp!'])->assertRedirect();
        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertTrue((bool) $round2->proposals()->first()->is_chosen);

        // Khách nhắn tin.
        $this->post(route('public.track.message', $order->tracking_token), ['body' => 'Khi nào có file ạ?'])->assertRedirect();

        // Bàn giao — đơn khoá file tới khi trả đủ.
        $this->actingAs($owner);
        $this->post(route('orders.deliver', $order->id), ['links' => [['url' => 'https://drive.google.com/drive/folders/final', 'label' => 'File gốc AI + PNG']]])->assertRedirect();
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertFalse($order->deliveryUnlocked());

        auth()->logout();
        $this->get(route('public.track', $order->tracking_token))->assertOk()
            ->assertSee('Link tải sẽ mở ngay khi thanh toán đủ')
            ->assertDontSee('drive.google.com/drive/folders/final')
            ->assertSee('Khi nào có file ạ?');

        // Thu nốt → link mở.
        $this->actingAs($owner);
        $this->post(route('orders.pay', $order->id), ['amount' => '1400000', 'paid_on' => now()->toDateString(), 'method' => 'tien_mat'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->paymentStatus());

        auth()->logout();
        $this->get(route('public.track', $order->tracking_token))->assertOk()->assertSee('drive.google.com/drive/folders/final')->assertSee('File gốc AI + PNG');

        // Báo cáo tháng có số thu.
        $this->actingAs($owner);
        $this->get(route('reports.index'))->assertOk()->assertSee('2.800.000');
        $this->get(route('reports.export'))->assertOk();
    }

    public function test_tracking_page_hides_internal_information(): void
    {
        $studio = $this->makeStudio('kin');
        $order = $this->placeOrder($studio);
        $order->forceFill(['internal_note' => 'Khách khó tính, báo giá cao lên'])->save();

        $this->actingAs($studio->owner);
        $this->post(route('rounds.store', $order->id));
        $round = $order->rounds()->first();
        $this->post(route('proposals.store', $round->id), ['label' => 'Bản nháp bí mật']);
        auth()->logout();

        $this->get(route('public.track', $order->tracking_token))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('Khách khó tính')
            ->assertDontSee('Bản nháp bí mật');

        // Đổi link → link cũ chết.
        $old = $order->tracking_token;
        $this->actingAs($studio->owner)->post(route('orders.token', $order->id))->assertRedirect();
        auth()->logout();
        $this->get(route('public.track', $old))->assertNotFound();
        $this->get(route('public.track', $order->fresh()->tracking_token))->assertOk();
    }

    public function test_closed_form_and_inactive_studio(): void
    {
        $studio = $this->makeStudio('dong', ['order_form_enabled' => false]);
        $this->get(route('public.order', 'dong'))->assertOk()->assertSee('Tạm ngừng nhận đơn mới');
        $this->post(route('public.order.store', 'dong'), $this->guard('order-'.$studio->id) + ['title' => 'x', 'brief' => 'y', 'name' => 'z', 'phone' => '0901234567'])->assertNotFound();

        $open = $this->makeStudio('mo-cua');
        $order = $this->placeOrder($open);
        $open->forceFill(['is_active' => false])->save();
        $this->get(route('public.order', 'mo-cua'))->assertNotFound();
        $this->get(route('public.track', $order->tracking_token))->assertNotFound();
    }

    public function test_order_form_rejects_bots_and_requires_policy(): void
    {
        $studio = $this->makeStudio('chat', ['policy' => 'Cọc 50% không hoàn.']);
        $payload = ['title' => 'Logo', 'brief' => 'Brief', 'name' => 'A', 'phone' => '0901234567'];

        // Thiếu token chống bot.
        $this->post(route('public.order.store', 'chat'), $payload)->assertSessionHasErrors('title');
        // Honeypot bị điền.
        $g = $this->guard('order-'.$studio->id);
        $g[$g['hp_key']] = 'http://spam';
        $this->post(route('public.order.store', 'chat'), $g + $payload)->assertSessionHasErrors('title');
        // Chưa đồng ý quy định.
        $this->post(route('public.order.store', 'chat'), $this->guard('order-'.$studio->id) + $payload)->assertSessionHasErrors('agree');
        // Link không hợp lệ.
        $this->post(route('public.order.store', 'chat'), $this->guard('order-'.$studio->id) + $payload + ['agree' => 1, 'links' => ['drive.google.com/abc']])->assertSessionHasErrors('links.0');

        $this->assertSame(0, Order::count());
        $this->post(route('public.order.store', 'chat'), $this->guard('order-'.$studio->id) + $payload + ['agree' => 1])->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_delivered_requires_source_link_and_order_with_payments_cannot_be_deleted(): void
    {
        $studio = $this->makeStudio('giao');
        $order = $this->placeOrder($studio);
        $this->actingAs($studio->owner);

        $this->patchJson(route('orders.status', $order->id), ['status' => 'delivered'])->assertStatus(422);
        $this->patchJson(route('orders.status', $order->id), ['status' => 'in_progress'])->assertOk();
        $this->assertSame('in_progress', $order->fresh()->status);

        $this->put(route('orders.charges', $order->id), ['charges' => [['kind' => 'base', 'amount' => '1000000']], 'deposit_percent' => 30]);
        $this->post(route('orders.pay', $order->id), ['amount' => '300000', 'paid_on' => now()->toDateString(), 'method' => 'chuyen_khoan']);
        $this->delete(route('orders.destroy', $order->id))->assertSessionHasErrors();
        $this->assertNotNull($order->fresh());
    }

    public function test_designer_created_order_with_new_client(): void
    {
        $studio = $this->makeStudio('tu-tao');
        $this->actingAs($studio->owner);
        $this->get(route('orders.create'))->assertOk();

        $this->post(route('orders.store'), [
            'client_name' => 'Anh Khoa', 'client_phone' => '+84 912 000 111',
            'title' => 'Poster hội chợ sách', 'priority' => 'urgent', 'brief' => 'A2, in offset',
            'revision_rounds' => 3, 'deposit_percent' => 40, 'due_on' => now()->addDays(5)->toDateString(),
            'links' => [['url' => 'https://www.figma.com/file/abc', 'label' => 'Moodboard']],
            'cover' => UploadedFile::fake()->image('cover.jpg', 2400, 1600),
        ])->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertSame('Anh Khoa', $order->client->name);
        $this->assertSame('0912000111', $order->client->phone);
        $this->assertTrue($order->isUrgent());
        $this->assertNotNull($order->cover_path);
        $this->get(route('orders.board'))->assertOk()->assertSee('Poster hội chợ sách');
        $this->get(route('orders.calendar'))->assertOk();
        $this->get(route('clients.show', $order->client_id))->assertOk()->assertSee('Poster hội chợ sách');
    }
}
