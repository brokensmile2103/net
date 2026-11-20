<?php

namespace Tests\Feature;

use App\Services\PriceListImage;

/** Ảnh bảng giá (PNG) + 2 nút Tải ảnh / Sao chép ở trang Dịch vụ & bảng giá. */
class PriceListImageTest extends NetTestCase
{
    private function png($response): string
    {
        return $response->baseResponse->getContent();
    }

    public function test_services_page_shows_download_and_copy_buttons(): void
    {
        $studio = $this->makeStudio('co-gia', ['page_theme' => 'riso']);
        $this->actingAs($studio->owner);

        $this->get(route('services.index'))->assertOk()
            ->assertSee('Tải ảnh về')
            ->assertSee('Sao chép ảnh')
            ->assertSee('netPriceImage(', false)
            ->assertSee('bang-gia-co-gia.png', false)
            ->assertSee('In Risograph');
    }

    public function test_buttons_hidden_when_no_public_service(): void
    {
        $studio = $this->makeStudio('trong');
        $studio->services()->update(['is_public' => false]);
        $this->actingAs($studio->owner);

        $this->get(route('services.index'))->assertOk()->assertDontSee('Tải ảnh về');
        $this->get(route('services.price-image'))->assertNotFound();
    }

    public function test_guest_cannot_get_the_image(): void
    {
        $this->makeStudio('khach-la');
        $this->get(route('services.price-image'))->assertRedirect();
    }

    public function test_image_is_a_png_for_every_theme_and_differs_between_themes(): void
    {
        $studio = $this->makeStudio('du-mau');
        $this->actingAs($studio->owner);

        $hashes = [];
        foreach (array_keys(config('net.page_themes')) as $theme) {
            $studio->update(['page_theme' => $theme]);
            $res = $this->get(route('services.price-image'))->assertOk()->assertHeader('Content-Type', 'image/png');
            $body = $this->png($res);
            $info = getimagesizefromstring($body);
            $this->assertSame(IMAGETYPE_PNG, $info[2], $theme);
            $this->assertSame(1200, $info[0], $theme);
            $this->assertGreaterThan(300, $info[1], $theme);
            $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
            $hashes[$theme] = md5($body);
            if ($dir = getenv('NET_DUMP_PRICELIST')) {
                @mkdir($dir, 0777, true);
                file_put_contents($dir.'/'.$theme.'.png', $body);
            }
        }
        $this->assertCount(count($hashes), array_unique($hashes), 'Mỗi mẫu giao diện phải ra một ảnh khác nhau.');
    }

    public function test_accent_color_changes_the_image(): void
    {
        $studio = $this->makeStudio('mau-nhan', ['accent_color' => 'custom', 'accent_custom' => '#14804a']);
        $this->actingAs($studio->owner);
        $a = md5($this->png($this->get(route('services.price-image'))->assertOk()));

        $studio->update(['accent_custom' => '#6440d0']);
        $b = md5($this->png($this->get(route('services.price-image'))->assertOk()));

        $this->assertNotSame($a, $b);
    }

    public function test_download_sets_attachment_header(): void
    {
        $studio = $this->makeStudio('tai-ve');
        $this->actingAs($studio->owner);

        $res = $this->get(route('services.price-image', ['tai' => 1]))->assertOk();
        $this->assertSame('attachment; filename="bang-gia-tai-ve.png"', $res->headers->get('Content-Disposition'));

        $inline = $this->get(route('services.price-image'))->assertOk();
        $this->assertNull($inline->headers->get('Content-Disposition'));
    }

    public function test_payload_matches_public_page_hides_hidden_services_and_stays_in_own_studio(): void
    {
        $a = $this->makeStudio('studio-a');
        $b = $this->makeStudio('studio-b');
        $a->services()->create(['name' => 'Dịch vụ ẩn của A', 'price' => 1000, 'is_public' => false, 'sort_order' => 99]);
        $b->services()->create(['name' => 'Chỉ có ở B', 'price' => 2000, 'is_public' => true, 'sort_order' => 99]);

        $names = fn ($studio) => array_column(app(PriceListImage::class)->payload($studio)['services'], 'name');

        $this->assertNotContains('Dịch vụ ẩn của A', $names($a));
        $this->assertNotContains('Chỉ có ở B', $names($a));
        $this->assertContains('Chỉ có ở B', $names($b));
        $this->assertSame(
            $a->services()->where('is_public', true)->orderBy('sort_order')->orderBy('id')->pluck('name')->all(),
            $names($a),
            'Cùng thứ tự và cùng danh sách với trang đặt đơn công khai.'
        );
    }

    public function test_payload_does_not_leak_internal_fields_and_caps_long_lists(): void
    {
        $studio = $this->makeStudio('dai');
        $studio->services()->delete();
        for ($i = 1; $i <= PriceListImage::MAX_SERVICES + 5; $i++) {
            $studio->services()->create(['name' => 'DV '.$i, 'price' => $i * 1000, 'is_public' => true, 'sort_order' => $i]);
        }
        $payload = app(PriceListImage::class)->payload($studio);

        $this->assertCount(PriceListImage::MAX_SERVICES, $payload['services']);
        $this->assertSame(5, $payload['more']);
        $this->assertSame(['name', 'desc', 'price', 'known', 'meta'], array_keys($payload['services'][0]));

        $this->actingAs($studio->owner);
        $this->get(route('services.price-image'))->assertOk();
    }

    public function test_unknown_price_and_missing_contact_still_render(): void
    {
        $studio = $this->makeStudio('bao-gia-sau', ['order_form_enabled' => false]);
        $studio->services()->delete();
        $studio->services()->create(['name' => 'Chỉ nhận báo giá', 'price' => null, 'is_public' => true]);
        $this->actingAs($studio->owner);

        $this->get(route('services.price-image'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertNull(app(PriceListImage::class)->payload($studio)['url']);
    }

    public function test_renderer_returns_null_instead_of_failing_when_fonts_are_missing(): void
    {
        $studio = $this->makeStudio('thieu-font');
        $images = new PriceListImage('/duong-dan-khong-ton-tai');

        $this->assertFalse($images->available());
        $this->assertNull($images->render($studio));
    }
}
