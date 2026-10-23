<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;

class GoogleAndSimpleFlowTest extends NetTestCase
{
    private function enableGoogle(): void
    {
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    /** Giả lập Google trả về 1 tài khoản. */
    private function fakeGoogle(string $id, ?string $email = 'linh@gmail.com', string $name = 'Hà Linh'): void
    {
        $user = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => $name, 'avatar' => null]);
        $provider = Mockery::mock();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    // ================= Google =================

    public function test_google_button_hidden_and_route_safe_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);
        $this->get('/login')->assertOk()->assertDontSee('Tiếp tục với Google');
        $this->get(route('auth.google'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->enableGoogle();
        $this->get('/login')->assertOk()->assertSee('Tiếp tục với Google');
        $this->get('/dang-ky')->assertOk()->assertSee('Tạo studio bằng Google');
    }

    public function test_new_google_user_completes_studio_signup(): void
    {
        $this->enableGoogle();
        $this->fakeGoogle('g-123');

        $this->get(route('auth.google'))->assertRedirect();
        $this->get(route('auth.google.callback'))->assertRedirect(route('register.google'));
        $this->assertSame(0, User::count());

        $this->get(route('register.google'))->assertOk()->assertSee('linh@gmail.com');

        $this->post(route('register.google.store'), $this->guard('register-google') + [
            'studio_name' => 'Hà Linh Design', 'slug' => 'ha-linh', 'name' => 'Hà Linh', 'phone' => '0912 888 999', 'terms' => 1,
        ])->assertRedirect(route('today'));

        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertFalse($user->has_password);
        $this->assertSame('linh@gmail.com', $user->email);
        $this->assertSame('0912888999', $user->phone);
        $this->assertSame('ha-linh', $user->studio->slug);
        $this->assertGreaterThan(0, $user->studio->services()->count());

        // Phiên Google đã dùng xong → không tạo được studio thứ 2.
        auth()->logout();
        $this->get(route('register.google'))->assertRedirect(route('register'));

        // Lần sau: đăng nhập thẳng.
        $this->get(route('auth.google.callback'))->assertRedirect(route('today'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_signup_validates_and_requires_pending_session(): void
    {
        $this->enableGoogle();
        $this->post(route('register.google.store'), $this->guard('register-google') + ['studio_name' => 'X', 'slug' => 'xxx', 'name' => 'X', 'phone' => '0912888999', 'terms' => 1])
            ->assertRedirect(route('register'));

        $this->makeStudio('da-co');
        $taken = User::first();
        $this->fakeGoogle('g-9', 'khac@gmail.com');
        $this->get(route('auth.google.callback'));
        $this->post(route('register.google.store'), $this->guard('register-google') + ['studio_name' => 'X', 'slug' => 'da-co', 'name' => 'X', 'phone' => $taken->phone])
            ->assertSessionHasErrors(['slug', 'phone', 'terms']);
        $this->post(route('register.google.store'), ['studio_name' => 'X', 'slug' => 'moi-tinh', 'name' => 'X', 'phone' => '0912000555', 'terms' => 1])
            ->assertSessionHasErrors('studio_name');
        $this->assertSame(1, User::count());
    }

    public function test_google_email_collision_does_not_auto_link(): void
    {
        $this->enableGoogle();
        $studio = $this->makeStudio('co-email');
        $studio->owner->forceFill(['email' => 'linh@gmail.com'])->save();
        $this->fakeGoogle('g-attacker', 'linh@gmail.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($studio->owner->fresh()->google_id);
    }

    public function test_link_set_password_unlink_and_delete_google_account(): void
    {
        $this->enableGoogle();
        $studio = $this->makeStudio('lien-ket');
        $user = $studio->owner;
        $this->actingAs($user);
        $this->get(route('profile.edit'))->assertOk()->assertSee('Liên kết Google');

        $this->fakeGoogle('g-link');
        $this->get(route('auth.google', ['lien-ket' => 1]))->assertRedirect();
        $this->get(route('auth.google.callback'))->assertRedirect(route('profile.edit'));
        $this->assertSame('g-link', $user->fresh()->google_id);
        $this->get(route('profile.edit'))->assertSee('Đã liên kết');

        // Tài khoản Google đã gắn với người khác → không cho liên kết.
        $other = $this->makeStudio('nguoi-khac')->owner;
        $this->actingAs($other);
        $this->get(route('auth.google', ['lien-ket' => 1]));
        $this->get(route('auth.google.callback'))->assertRedirect(route('profile.edit'))->assertSessionHasErrors('google');
        $this->assertNull($other->fresh()->google_id);

        // Có mật khẩu → gỡ được.
        $this->actingAs($user);
        $this->delete(route('profile.google.unlink'))->assertRedirect();
        $this->assertNull($user->fresh()->google_id);

        // Tài khoản tạo bằng Google (chưa có mật khẩu): không gỡ được, đặt mật khẩu không cần mật khẩu cũ, xoá chỉ cần cụm xác nhận.
        $g = $this->makeStudio('chi-google')->owner;
        $g->forceFill(['google_id' => 'g-only', 'has_password' => false])->save();
        $this->actingAs($g);
        $this->get(route('profile.edit'))->assertSee('Đặt mật khẩu');
        $this->delete(route('profile.google.unlink'))->assertSessionHasErrors('google');
        $this->assertSame('g-only', $g->fresh()->google_id);
        $this->delete(route('profile.destroy'), ['confirmation' => 'XOA TAI KHOAN'])->assertRedirect(route('login'));
        $this->assertNull(User::find($g->id));

        $h = $this->makeStudio('dat-mk')->owner;
        $h->forceFill(['google_id' => 'g-h', 'has_password' => false])->save();
        $this->actingAs($h);
        $this->put(route('profile.password'), ['password' => 'matkhaumoi1', 'password_confirmation' => 'matkhaumoi1'])->assertRedirect();
        $this->assertTrue($h->fresh()->has_password);
        $this->put(route('profile.password'), ['password' => 'khac12345', 'password_confirmation' => 'khac12345'])->assertSessionHasErrors('current_password');
    }

    public function test_google_login_blocked_for_locked_studio_or_user(): void
    {
        $this->enableGoogle();
        $studio = $this->makeStudio('bi-khoa');
        $studio->owner->forceFill(['google_id' => 'g-lock'])->save();
        $studio->forceFill(['is_active' => false])->save();
        $this->fakeGoogle('g-lock');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    // ================= Google: lỗi không được thành trang 500 =================

    public function test_google_cancel_returns_to_login(): void
    {
        $this->enableGoogle();

        // Khách bấm "Huỷ" ở màn hình Google.
        $this->get(route('auth.google.callback', ['error' => 'access_denied']))->assertRedirect(route('login'))->assertSessionHasErrors('google');
    }

    public function test_google_redirect_failure_is_not_500(): void
    {
        $this->enableGoogle();

        // Socialite không khởi tạo được (thiếu thư viện / cấu hình sai) ở bước chuyển sang Google.
        $broken = Mockery::mock();
        $broken->shouldReceive('with')->andThrow(new \RuntimeException('Target class [Laravel\Socialite\Contracts\Factory] does not exist.'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($broken);

        $this->get(route('auth.google'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
    }

    public function test_google_callback_server_error_is_not_500(): void
    {
        $this->enableGoogle();

        // Google trả về được nhưng phía Nét lỗi (VD: database thiếu cột google_id).
        $user = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $user->shouldReceive('getId')->andThrow(new \RuntimeException('SQLSTATE[42S22]: Column not found: google_id'));
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_invalid_state_shows_friendly_error(): void
    {
        $this->enableGoogle();
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new \Laravel\Socialite\Two\InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
    }

    public function test_schema_updater_stamps_latest_migration_and_admin_can_trigger_it(): void
    {
        $updater = app(\App\Services\SchemaUpdater::class);
        $stamp = $updater->stampPath();
        $backup = is_file($stamp) ? file_get_contents($stamp) : null;
        @unlink($stamp);

        try {
            $this->assertFalse($updater->upToDate());
            $this->assertSame('2026_10_23_000009_add_briefs_reminders_consultations', $updater->latestMigration());

            $this->actingAs($this->admin());
            $this->get(route('admin.dashboard'))->assertOk()->assertSee('Cập nhật database ngay');
            $this->post(route('admin.schema.update'))->assertRedirect();

            $this->assertTrue($updater->upToDate());
            $this->assertNull($updater->lastError());
            $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Cập nhật database ngay');
        } finally {
            $backup === null ? @unlink($stamp) : file_put_contents($stamp, $backup);
        }
    }

    // ================= Đơn đơn giản, không cọc =================

    public function test_simple_order_without_deposit(): void
    {
        $studio = $this->makeStudio('don-gian', ['default_deposit_percent' => 0, 'bank_code' => 'MB', 'bank_account' => '999888', 'lock_delivery_until_paid' => true]);
        $service = $studio->services()->first();
        $service->update(['price' => 1500000, 'price_is_from' => false, 'deposit_percent' => null]);

        $this->get(route('public.order', 'don-gian'))->assertSee('không cần cọc');

        $this->post(route('public.order.store', 'don-gian'), $this->guard('order-'.$studio->id) + [
            'service_id' => $service->id, 'title' => 'Avatar fanpage', 'brief' => 'Logo tròn', 'name' => 'Bé Na', 'phone' => '0909111222',
        ])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertEquals(1500000, (float) $order->total_amount);
        $this->assertTrue($order->noDeposit());
        $this->assertEquals(0, $order->nextPaymentAmount());

        // Freelancer nhận đơn, không cần gửi báo giá / chờ khách đồng ý.
        $this->actingAs($studio->owner);
        $this->get(route('orders.show', $order->id))->assertSee('Đơn mới — bắt đầu thế nào?');
        $this->post(route('orders.accept', $order->id))->assertRedirect();
        $this->assertSame('in_progress', $order->fresh()->status);
        $this->post(route('orders.accept', $order->id))->assertStatus(422);

        auth()->logout();
        $this->get(route('public.track', $order->tracking_token))->assertOk()
            ->assertSee('Không cần đặt cọc')
            ->assertSee('Chi phí')
            ->assertDontSee('img.vietqr.io', false);

        // Gửi 1 phương án, khách duyệt.
        $this->actingAs($studio->owner);
        $this->post(route('rounds.store', $order->id));
        $round = $order->rounds()->first();
        $this->post(route('proposals.store', $round->id), ['label' => 'A', 'images' => [UploadedFile::fake()->image('a.jpg', 600, 600)]]);
        $this->post(route('rounds.send', $round->id));
        auth()->logout();
        $this->post(route('public.track.respond', $order->tracking_token), ['decision' => 'approve'])->assertRedirect();
        $order->refresh();
        $this->assertSame('approved', $order->status);

        // Sau khi duyệt mới hiện mã chuyển khoản, đúng toàn bộ số tiền.
        $this->assertEquals(1500000, $order->nextPaymentAmount());
        $this->get(route('public.track', $order->tracking_token))->assertSee('amount=1500000', false);

        // Thu 1 lần → không bị ghi là "cọc"; bàn giao mở khoá ngay.
        $this->actingAs($studio->owner);
        $this->post(route('orders.pay', $order->id), ['amount' => '1000000', 'paid_on' => now()->toDateString(), 'method' => 'chuyen_khoan']);
        $this->assertFalse((bool) $order->payments()->first()->is_deposit);
        $this->assertSame('partial', $order->fresh()->paymentStatus());
        $this->post(route('orders.pay', $order->id), ['amount' => '500000', 'paid_on' => now()->toDateString(), 'method' => 'chuyen_khoan']);
        $this->post(route('orders.deliver', $order->id), ['links' => [['url' => 'https://drive.google.com/drive/folders/na']]]);
        $this->assertTrue($order->fresh()->deliveryUnlocked());
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_accept_can_drop_deposit_on_the_spot(): void
    {
        $studio = $this->makeStudio('bo-coc');
        $client = $studio->clients()->create(['name' => 'K', 'phone' => '0900000009']);
        $order = app(\App\Services\OrderService::class)->create($studio, $client, ['title' => 'T', 'brief' => 'B'], 'manual', $studio->services()->first());
        $this->assertSame(50, (int) $order->deposit_percent);

        $this->actingAs($studio->owner)->post(route('orders.accept', $order->id), ['no_deposit' => 1])->assertRedirect();
        $order->refresh();
        $this->assertSame(0, (int) $order->deposit_percent);
        $this->assertSame('in_progress', $order->status);
    }
}
