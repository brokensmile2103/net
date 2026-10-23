<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Studio;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** 3 trang tĩnh công khai (nội dung lấy từ database), sitemap và robots.txt. */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('pages.show', compact('page'));
    }

    /**
     * Sitemap chỉ liệt kê các trang CÔNG KHAI và ĐÁNG được index (không đưa
     * /login vào vì trang đó cố tình đặt noindex). Build URL bằng route()
     * để luôn khớp đúng domain thật đang chạy, không hardcode domain.
     */
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('register'), 'priority' => '0.9'],
            ['loc' => route('pages.about'), 'priority' => '0.7'],
            ['loc' => route('pages.terms'), 'priority' => '0.3'],
            ['loc' => route('pages.privacy'), 'priority' => '0.3'],
        ];

        // Đăng ký đang đóng thì không đưa trang đăng ký vào sitemap.
        if (! \App\Models\PlatformSetting::registrationOpen()) {
            $urls = array_values(array_filter($urls, fn ($u) => $u['loc'] !== route('register')));
        }

        // Trang đặt đơn công khai của các studio đang mở nhận đơn — giúp khách tìm thấy freelancer.
        Studio::query()->where('is_active', true)->where('order_form_enabled', true)->orderBy('id')->limit(5000)->pluck('slug')
            ->each(function ($slug) use (&$urls) {
                $urls[] = ['loc' => route('public.order', $slug), 'priority' => '0.6'];
            });

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * robots.txt động — dòng "Sitemap:" phải là URL tuyệt đối nên build lúc chạy bằng url().
     * Chặn khu làm việc (cần đăng nhập) và trang theo dõi đơn (link riêng của khách, không được index).
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /theo-doi/',
            'Disallow: /hom-nay',
            'Disallow: /don',
            'Disallow: /vong/',
            'Disallow: /phuong-an/',
            'Disallow: /khach',
            'Disallow: /dich-vu',
            'Disallow: /bao-cao',
            'Disallow: /cai-dat',
            'Disallow: /ho-so',
            'Disallow: /sieu-quan-tri',
            'Disallow: /install',
            'Disallow: /login',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain');
    }
}
