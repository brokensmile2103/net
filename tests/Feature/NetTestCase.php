<?php

namespace Tests\Feature;

use App\Models\Studio;
use App\Models\User;
use App\Services\FormGuard;
use App\Services\StudioSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class NetTestCase extends TestCase
{
    use RefreshDatabase;

    private bool $createdLock = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(storage_path('installed.lock'))) {
            file_put_contents(storage_path('installed.lock'), 'test');
            $this->createdLock = true;
        }

        Storage::fake('public');
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->createdLock) {
            @unlink(storage_path('installed.lock'));
        }

        parent::tearDown();
    }

    protected function makeStudio(string $slug = 'linh-studio', array $attrs = []): Studio
    {
        static $phone = 912000000;
        $phone++;

        $user = User::forceCreate([
            'name' => 'Linh '.$slug,
            'phone' => '0'.$phone,
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $studio = new Studio(array_merge(['name' => 'Studio '.$slug, 'slug' => $slug], $attrs));
        $studio->owner_id = $user->id;
        $studio->is_active = true;
        $studio->save();
        app(StudioSetup::class)->seedServices($studio);

        return $studio->fresh();
    }

    protected function admin(): User
    {
        return User::forceCreate([
            'name' => 'Quản trị',
            'phone' => '0999999999',
            'password' => Hash::make('password123'),
            'role' => 'platform_admin',
            'is_active' => true,
        ]);
    }

    /** Các field chống bot cho form công khai. */
    protected function guard(string $name): array
    {
        $g = FormGuard::generate($name);

        // Giả lập người dùng thật: form hiển thị cách đây vài giây.
        $ts = $g['timestamp'] - 5;
        $sig = (fn () => self::sign($name, $ts, $g['honeypot_field']))->call(new FormGuard);

        return ['form_ts' => (string) $ts, 'form_sig' => $sig, 'hp_key' => $g['honeypot_field'], $g['honeypot_field'] => ''];
    }
}
