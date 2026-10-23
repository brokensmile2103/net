<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Studio;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;

/**
 * Super Admin dùng được như freelancer (có studio riêng, không bị ép về /sieu-quan-tri)
 * và việc đóng/mở đăng ký công khai.
 */
class PlatformAdminTest extends NetTestCase
{
    private function fakeGoogle(string $id, ?string $email = null): void
    {
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
        $user = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => 'Khách', 'avatar' => null]);
        $provider = Mockery::mock();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_platform_admin_without_studio_gets_one_on_login_and_lands_on_work_area(): void
    {
        $admin = $this->admin();
        $this->assertSame(0, Studio::count());

        $this->post('/login', ['phone' => $admin->phone, 'password' => 'password123'])->assertRedirect(route('today'));

        $studio = Studio::where('owner_id', $admin->id)->first();
        $this->assertNotNull($studio);
        $this->assertTrue($studio->is_active);
        $this->assertGreaterThan(0, $studio->services()->count());
    }

    public function test_platform_admin_can_use_work_area_and_admin_area_without_redirects(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('today'))->assertOk();
        $this->get(route('settings.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.settings.edit'))->assertOk();
    }

    public function test_platform_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->delete(route('profile.destroy'), ['delete_password' => 'password123', 'confirmation' => 'XOA TAI KHOAN'])->assertForbidden();
        $this->assertNotNull($admin->fresh());
    }

    public function test_regular_owner_cannot_open_admin_settings(): void
    {
        $studio = $this->makeStudio('thuong');
        $this->actingAs($studio->owner)->get(route('admin.settings.edit'))->assertForbidden();
        $this->put(route('admin.settings.update'), ['registration_open' => '0'])->assertForbidden();
    }

    public function test_admin_can_close_and_reopen_registration(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(PlatformSetting::registrationOpen());

        $this->put(route('admin.settings.update'), ['registration_open' => '0'])->assertRedirect();
        $this->assertFalse(PlatformSetting::registrationOpen());

        $this->put(route('admin.settings.update'), ['registration_open' => '1'])->assertRedirect();
        $this->assertTrue(PlatformSetting::registrationOpen());
    }

    public function test_closed_registration_blocks_register_pages_and_unknown_google_accounts(): void
    {
        PlatformSetting::put('registration_open', '0');

        $this->get('/dang-ky')->assertRedirect(route('login'));
        $this->post('/dang-ky', [])->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertDontSee('Tạo studio miễn phí');
        $this->assertSame(0, User::count());

        $this->fakeGoogle('g-unknown', 'la@example.com');
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame(0, User::count());
        $this->assertNull(session('google_pending'));
    }

    public function test_closed_registration_still_lets_existing_google_account_sign_in(): void
    {
        PlatformSetting::put('registration_open', '0');
        $admin = $this->admin();
        $admin->forceFill(['google_id' => 'g-admin'])->save();

        $this->fakeGoogle('g-admin');
        $this->get(route('auth.google.callback'))->assertRedirect(route('today'));
        $this->assertAuthenticatedAs($admin);
    }
}
