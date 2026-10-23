<?php

namespace Tests\Feature;

use App\Services\ShareImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Thẻ chia sẻ (Open Graph / Twitter) và ảnh xem trước 1200×630 của trang đặt đơn. */
class ShareImageTest extends NetTestCase
{
    public function test_order_page_has_complete_share_tags(): void
    {
        $studio = $this->makeStudio('may-studio', [
            'name' => 'Mây Studio', 'tagline' => 'Logo & bao bì cho tiệm nhỏ', 'page_headline' => 'Vẽ cho thương hiệu nhỏ mà có võ',
        ]);
        $html = $this->get(route('public.order', 'may-studio').'?utm_source=zalo')->assertOk()->getContent();

        $canonical = route('public.order', 'may-studio');
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.$canonical.'">', $html, 'og:url bỏ query (utm, xem-thu).');
        $this->assertStringContainsString('<meta property="og:site_name" content="Mây Studio">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="vi_VN">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Mây Studio · Đặt thiết kế">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringContainsString('<meta property="og:image:height" content="630">', $html);
        $this->assertStringContainsString('<meta property="og:image:type" content="image/jpeg">', $html);

        // Mô tả: câu chào + khẩu hiệu + dịch vụ đang nhận, 1 dòng, không quá dài.
        preg_match('/<meta property="og:description" content="([^"]*)">/', $html, $m);
        $desc = html_entity_decode($m[1]);
        $this->assertStringStartsWith('Vẽ cho thương hiệu nhỏ mà có võ · Logo & bao bì cho tiệm nhỏ · Nhận: ', $desc);
        $this->assertLessThanOrEqual(200, mb_strlen($desc));
        $this->assertStringNotContainsString("\n", $desc);

        // Ảnh: link tuyệt đối, có ?v= mã nội dung.
        preg_match('/<meta property="og:image" content="([^"]+)">/', $html, $m);
        $image = html_entity_decode($m[1]);
        $this->assertStringStartsWith(route('public.share-image', 'may-studio').'?v=', $image);
        $this->assertStringContainsString('<meta name="twitter:image" content="'.e($image).'">', $html);
    }

    public function test_share_image_is_a_1200x630_jpeg_and_refreshes_when_content_changes(): void
    {
        $studio = $this->makeStudio('anh-chia-se', ['name' => 'Xưởng Vẽ Ổi Ơi', 'page_headline' => 'Nhận diện thương hiệu, bao bì, minh hoạ — làm kỹ từng nét']);
        $first = $studio->shareImageUrl();

        $res = $this->get($first)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('max-age=604800', $res->headers->get('Cache-Control'));
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($this->body($res)), 0, 2));
        $this->dump('1-no-cover-no-works', $res);

        // Thêm tác phẩm (chưa có ảnh bìa) → ảnh mới, file cũ bị xoá.
        $this->actingAs($studio->owner);
        $this->post(route('portfolio.store'), ['works' => [$this->photo('a', 1200, 900), $this->photo('b', 900, 1100), $this->photo('c', 1000, 800)]])->assertRedirect();
        $studio->refresh();
        $second = $studio->shareImageUrl();
        $this->assertNotSame($first, $second);
        $this->dump('2-works', $this->get($second)->assertOk());
        $this->assertCount(1, Storage::disk('public')->files($studio->storageDir('og')), 'Chỉ giữ ảnh chia sẻ mới nhất.');

        // Có ảnh bìa + ảnh đại diện → bố cục ảnh bìa tràn khung.
        $this->post(route('settings.page-cover'), ['cover' => $this->photo('bia', 2400, 1200)])->assertRedirect();
        $this->post(route('settings.avatar'), ['avatar' => $this->photo('avatar', 600, 600)])->assertRedirect();
        $studio->refresh();
        $third = $studio->shareImageUrl();
        $this->assertNotSame($second, $third);
        $res = $this->get($third)->assertOk();
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($this->body($res)), 0, 2));
        $this->dump('3-cover', $res);

        // Không đổi gì → cùng link (Facebook/Zalo dùng lại ảnh trong bộ nhớ đệm).
        $this->assertSame($third, $studio->fresh()->shareImageUrl());

        // Đổi tên → link đổi.
        $studio->forceFill(['name' => 'Ổi Ơi Studio'])->save();
        $this->assertNotSame($third, $studio->fresh()->shareImageUrl());
    }

    public function test_long_names_and_custom_colors_still_render(): void
    {
        $studio = $this->makeStudio('ten-dai', [
            'name' => 'Công Ty Trách Nhiệm Hữu Hạn Thiết Kế Sáng Tạo Đồ Hoạ Và Truyền Thông Ánh Dương Rực Rỡ',
            'page_headline' => str_repeat('Thiết kế nhận diện thương hiệu trọn gói cho quán cà phê, tiệm bánh, cửa hàng thời trang. ', 4),
            'accent_color' => 'custom', 'accent_custom' => '#f5e663',
        ]);
        $res = $this->get($studio->shareImageUrl())->assertOk();
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($this->body($res)), 0, 2));
        $this->dump('4-long-yellow', $res);
    }

    public function test_tracking_page_shares_order_title_and_inactive_studio_has_no_image(): void
    {
        $studio = $this->makeStudio('theo-doi-og');
        $this->post(route('public.order.store', 'theo-doi-og'), [
            'title' => 'Logo quán chè', 'brief' => 'Quán chè nhỏ ở Huế', 'name' => 'Hà', 'phone' => '0901234567',
        ] + $this->guard('order-'.$studio->id))->assertRedirect();
        $order = $studio->orders()->first();

        $html = $this->get(route('public.track', $order->tracking_token))->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:title" content="Đơn '.$order->code.' · Logo quán chè">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);

        $studio->forceFill(['is_active' => false])->save();
        $this->get(route('public.share-image', 'theo-doi-og'))->assertNotFound();
    }

    public function test_headline_falls_back_and_description_is_clean(): void
    {
        $studio = $this->makeStudio('mo-ta', ['bio' => "Mình là Linh.\n\nVẽ logo <b>5 năm</b>."]);
        $studio->services()->update(['is_public' => false]);
        $this->assertSame('Mình là Linh. Vẽ logo 5 năm.', $studio->shareDescription([]));
        $this->assertNotSame('', app(ShareImage::class)->headline($studio));
    }

    private function body($response): string
    {
        $file = $response->baseResponse->getFile();

        return file_get_contents($file->getPathname());
    }

    /** Ảnh thử có chuyển màu + hình khối (giống ảnh thật hơn ảnh 1 màu) để xem bố cục bằng mắt. */
    private function photo(string $name, int $w, int $h): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        $seed = crc32($name);
        [$r1, $g1, $b1] = [($seed >> 3) % 200 + 30, ($seed >> 9) % 200 + 30, ($seed >> 15) % 200 + 30];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, (int) ($r1 * (1 - $t) + 250 * $t), (int) ($g1 * (1 - $t) + 220 * $t), (int) ($b1 * (1 - $t) + 180 * $t)));
        }
        imagefilledellipse($img, (int) ($w * .62), (int) ($h * .45), (int) ($w * .42), (int) ($w * .42), imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, (int) ($w * .15), (int) ($h * .6), (int) ($w * .4), (int) ($h * .9), imagecolorallocate($img, 30, 30, 40));
        $path = tempnam(sys_get_temp_dir(), 'og').'.jpg';
        imagejpeg($img, $path, 90);

        return new UploadedFile($path, $name.'.jpg', 'image/jpeg', null, true);
    }

    /** NET_DUMP_SHARE=/thu-muc → lưu ảnh ra để xem bằng mắt khi chỉnh bố cục. */
    private function dump(string $name, $response): void
    {
        if ($dir = getenv('NET_DUMP_SHARE')) {
            @mkdir($dir, 0777, true);
            file_put_contents($dir.'/'.$name.'.jpg', $this->body($response));
        }
    }
}
