<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Order;
use App\Models\PageVisit;
use App\Models\PlatformSetting;
use App\Models\Studio;
use App\Notifications\ClientReminder;
use App\Notifications\StageUpdate;
use App\Services\Analytics\PageAnalytics;
use App\Services\Mail\PlatformMail;
use App\Services\Mail\StageMail;
use App\Services\OrderService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Nét v6.2: thống kê trang đặt đơn, favicon, Google Analytics, email gửi khách (Resend của Nét, sandbox, giai đoạn). */
class V62Test extends NetTestCase
{
    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    // ------------------------------------------------------------------ Thống kê

    public function test_visits_are_counted_per_visitor_per_day_with_source_and_device(): void
    {
        $studio = $this->makeStudio('thong-ke');
        $url = route('public.order', $studio->slug);

        // Facebook in-app (không có Referer) → facebook; xem 2 lần = 1 người, 2 lượt.
        $fb = self::PHONE_UA.' [FBAN/FBIOS;FBAV/450.0]';
        $this->withHeaders(['User-Agent' => $fb])->get($url)->assertOk();
        $this->withHeaders(['User-Agent' => $fb])->get($url)->assertOk();
        // Link gắn nguồn ?nguon=zalo, máy tính.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130'])->get($url.'?nguon=zalo')->assertOk();
        // Referer Google.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh) Safari/605', 'Referer' => 'https://www.google.com.vn/'])->get($url)->assertOk();
        // Bot xem trước link & chủ studio: không tính.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])->withHeaders(['User-Agent' => 'facebookexternalhit/1.1'])->get($url)->assertOk();
        $this->actingAs($studio->owner)->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/130'])->get($url)->assertOk();

        $rows = PageVisit::query()->where('studio_id', $studio->id)->get();
        $this->assertCount(3, $rows);
        $fbRow = $rows->firstWhere('source', 'facebook');
        $this->assertSame(2, $fbRow->views);
        $this->assertSame('mobile', $fbRow->device);
        $this->assertSame('desktop', $rows->firstWhere('source', 'zalo')->device);
        $this->assertNotNull($rows->firstWhere('source', 'google'));
        // Dấu vân tay ngắn, không chứa IP.
        $this->assertSame(16, strlen($fbRow->visitor));
        $this->assertStringNotContainsString('10.0.0', $rows->pluck('visitor')->implode(','));
    }

    public function test_source_detection_rules(): void
    {
        $a = app(PageAnalytics::class);
        $req = fn (string $uri, array $headers = []) => tap(Request::create($uri, 'GET'), fn ($r) => $r->headers->add($headers));

        $this->assertSame('zalo', $a->source($req('/dat/x', ['User-Agent' => 'Mozilla/5.0 Zalo iOS'])));
        $this->assertSame('instagram', $a->source($req('/dat/x?utm_source=IG')));
        $this->assertSame('tiktok', $a->source($req('/dat/x', ['User-Agent' => 'Mozilla musical_ly_32.1'])));
        $this->assertSame('facebook', $a->source($req('/dat/x', ['Referer' => 'https://l.facebook.com/l.php?u=x'])));
        $this->assertSame('email', $a->source($req('/dat/x', ['Referer' => 'https://mail.google.com/'])));
        $this->assertSame('google', $a->source($req('/dat/x', ['Referer' => 'https://www.google.com/'])));
        $this->assertSame('other', $a->source($req('/dat/x', ['Referer' => 'https://blog.example.org/post'])));
        $this->assertSame('other', $a->source($req('/dat/x?nguon=bao-giay')));
        $this->assertSame('direct', $a->source($req('/dat/x')));
        $this->assertSame('tablet', $a->device('Mozilla/5.0 (iPad; CPU OS 17_0) Safari'));
    }

    public function test_order_keeps_visit_source_and_analytics_page_reports_it(): void
    {
        $studio = $this->makeStudio('nguon-don');
        $service = $studio->services()->first();
        $this->withHeaders(['User-Agent' => self::PHONE_UA.' Zalo'])->get(route('public.order', $studio->slug))->assertOk();
        $this->withHeaders(['User-Agent' => self::PHONE_UA.' Zalo'])->post(route('public.order.store', $studio->slug), $this->guard('order-'.$studio->id) + [
            'name' => 'Chị Hà', 'phone' => '0903111222', 'service_id' => $service->id, 'title' => 'Logo quán', 'brief' => 'Cần logo tối giản',
        ])->assertRedirect();

        $order = Order::query()->latest('id')->first();
        $this->assertSame('zalo', $order->ref_source);

        // Trang thống kê của chủ studio.
        $this->actingAs($studio->owner)->get(route('analytics.index', ['ngay' => 7]))->assertOk()
            ->assertSee('Thống kê trang đặt đơn')->assertSee('Zalo')->assertSee('Đơn gửi qua trang')->assertSee('?nguon=zalo', false);

        // Studio khác không thấy số liệu của studio này.
        $other = $this->makeStudio('studio-khac');
        $this->actingAs($other->owner)->get(route('analytics.index'))->assertOk()->assertSee('Chưa có lượt xem nào');
    }

    // ------------------------------------------------------------------ Favicon

    public function test_public_pages_use_studio_icon_and_icon_route_returns_png(): void
    {
        $studio = $this->makeStudio('icon-studio');
        $this->get(route('public.order', $studio->slug))->assertOk()
            ->assertSee('rel="apple-touch-icon" sizes="180x180" href="'.e($studio->iconUrl(180)), false)
            ->assertDontSee('href="/favicon.svg"', false);

        foreach ([32, 180] as $size) {
            $res = $this->get(route('public.icon', ['slug' => $studio->slug, 'size' => $size]));
            $res->assertOk();
            $png = file_get_contents($res->baseResponse->getFile()->getPathname());
            $this->assertStringStartsWith("\x89PNG", $png);
            $info = getimagesizefromstring($png);
            $this->assertSame([$size, $size], [$info[0], $info[1]]);
        }
        $this->get(route('public.icon', ['slug' => $studio->slug, 'size' => 99]))->assertNotFound();

        // Đổi màu nhấn → dấu vân tay mới → link icon mới.
        $before = $studio->iconUrl(32);
        $studio->forceFill(['accent_color' => 'custom', 'accent_custom' => '#3355ff'])->save();
        $this->assertNotSame($before, $studio->fresh()->iconUrl(32));
    }

    // ------------------------------------------------------------------ Google Analytics

    public function test_google_analytics_id_is_extracted_and_rendered_safely(): void
    {
        $studio = $this->makeStudio('ga-studio');
        $this->actingAs($studio->owner);

        // Dán cả đoạn mã gtag (kèm script lạ) → chỉ lấy mã G-…
        $this->put(route('settings.analytics'), ['ga_measurement_id' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-AB12CD34EF"></script><script>alert(1)</script>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('G-AB12CD34EF', $studio->fresh()->ga_measurement_id);
        $this->put(route('settings.analytics'), ['ga_measurement_id' => 'UA-123456-1'])->assertSessionHasErrors('ga_measurement_id');
        $this->assertSame('G-AB12CD34EF', $studio->fresh()->ga_measurement_id);

        auth()->logout();
        $page = $this->get(route('public.order', $studio->slug))->assertOk();
        $page->assertSee('googletagmanager.com/gtag/js?id=G-AB12CD34EF', false)->assertDontSee('alert(1)', false);

        // Chủ studio xem trang của mình: không gắn GA.
        $this->actingAs($studio->owner)->get(route('public.order', $studio->slug))->assertDontSee('googletagmanager', false);
        auth()->logout();

        // Trang theo dõi đơn: không có GA… trừ ngay sau khi gửi đơn (sự kiện generate_lead, KHÔNG lộ link theo dõi).
        $order = app(OrderService::class)->create($studio, $studio->clients()->create(['name' => 'K']), ['title' => 'Logo', 'brief' => 'b'], 'form', null, [], 'new');
        $this->get($order->trackingUrl())->assertOk()->assertDontSee('googletagmanager', false);
        $lead = $this->withSession(['ga_lead' => ['form' => 'order', 'service' => 'Logo']])->get($order->trackingUrl())->assertOk();
        $lead->assertSee('generate_lead', false)->assertSee('send_page_view', false);
        $this->assertStringNotContainsString($order->tracking_token, substr($lead->getContent(), strpos($lead->getContent(), 'gtag(\'js\''), 1500));

        // Gỡ.
        $this->actingAs($studio->owner)->put(route('settings.analytics'), ['ga_measurement_id' => '', 'remove' => 1]);
        $this->assertNull($studio->fresh()->ga_measurement_id);
    }

    // ------------------------------------------------------------------ Email: cấu hình Nét (Super Admin)

    public function test_admin_resend_settings_verify_domain_encrypt_key_and_apply(): void
    {
        $admin = $this->admin();
        Http::swap(new HttpFactory(app('events')));
        Http::fake(['api.resend.com/domains' => Http::response(['data' => [['name' => 'mail.net.vn', 'status' => 'verified'], ['name' => 'cho.vn', 'status' => 'pending']]], 200)]);

        // Tên miền chưa xác thực → không lưu.
        $this->actingAs($admin)->put(route('admin.settings.mail'), ['resend_key' => 're_test_1234567890', 'from_address' => 'a@cho.vn', 'studio_daily' => 20, 'platform_daily' => 100])
            ->assertSessionHasErrors('resend_key');
        $this->assertNull(PlatformSetting::get('mail_resend_key'));
        // Gmail → không lưu.
        $this->put(route('admin.settings.mail'), ['resend_key' => 're_test_1234567890', 'from_address' => 'a@gmail.com', 'studio_daily' => 20, 'platform_daily' => 100])
            ->assertSessionHasErrors('resend_key');

        $this->put(route('admin.settings.mail'), ['resend_key' => 're_test_1234567890', 'from_address' => 'thongbao@mail.net.vn', 'per_studio_address' => 1, 'studio_daily' => 20, 'platform_daily' => 100])
            ->assertSessionHasNoErrors();
        $stored = PlatformSetting::get('mail_resend_key');
        $this->assertNotSame('re_test_1234567890', $stored); // mã hoá
        $this->assertSame('resend', config('mail.default'));
        $this->assertSame('re_test_1234567890', config('services.resend.key'));
        $this->assertSame('thongbao@mail.net.vn', config('mail.from.address'));

        $studio = $this->makeStudio('linh-mail', ['email' => 'linh@gmail.com']);
        $this->assertSame(['linh-mail@mail.net.vn', 'Studio linh-mail'], app(PlatformMail::class)->fromFor($studio));
        $this->assertSame(20, app(PlatformMail::class)->studioLimit($studio));
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Đang dùng Resend cấu hình ở đây')->assertDontSee('re_test_1234567890');

        // Super Admin đặt hạn mức riêng / tạm dừng studio.
        $this->put(route('admin.studios.email', $studio->id), ['limit' => 5, 'blocked' => 1])->assertSessionHasNoErrors();
        $studio->refresh();
        $this->assertSame(5, app(PlatformMail::class)->studioLimit($studio));
        $this->assertTrue($studio->emailPrefs()['blocked']);
        // Studio không tự gỡ được "blocked" qua form của mình.
        $this->actingAs($studio->owner)->put(route('settings.email'), ['sender_name' => 'Linh', 'blocked' => 0, 'limit' => 999]);
        $this->assertTrue($studio->fresh()->emailPrefs()['blocked']);
        $this->assertSame(5, $studio->fresh()->emailPrefs()['limit']);
    }

    // ------------------------------------------------------------------ Email theo giai đoạn

    private function mailStudio(array $prefs = []): array
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'no-reply@net.test']);
        $studio = $this->makeStudio('mail-studio', ['email' => 'linh@may.vn', 'page_theme' => 'editorial']);
        $studio->forceFill(['email_prefs' => array_merge(['stages_on' => true, 'sender_name' => 'Linh Design', 'reply_to' => 'hoi@linh.vn'], $prefs)])->save();
        $client = $studio->clients()->create(['name' => 'Chị Lan', 'email' => 'lan.tran@gmail.com']);
        $order = app(OrderService::class)->create($studio, $client, ['title' => 'Logo Mây', 'brief' => 'B'], 'manual', null, [], 'new');

        return [$studio->fresh(), $order->fresh()];
    }

    public function test_stage_email_is_queued_with_delay_cancelled_on_undo_and_sent_branded(): void
    {
        Notification::fake();
        [$studio, $order] = $this->mailStudio();
        $outbox = app(StageMail::class);

        // Đơn tạo tay → không gửi "Đã nhận yêu cầu".
        $this->assertSame(0, EmailLog::count());

        // Chuyển sang Đang làm → xếp hàng chờ 3 phút.
        $order->forceFill(['status' => 'in_progress'])->save();
        $log = EmailLog::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(['pending', 'stage:in_progress'], [$log->status, $log->kind]);
        $this->assertTrue($log->send_at->isFuture());
        $this->assertSame(0, $outbox->processDue());

        // Kéo nhầm sang "Đang sửa" (giai đoạn đang tắt) → email cũ bị huỷ.
        $order->forceFill(['status' => 'revising'])->save();
        $this->assertSame('cancelled', $log->fresh()->status);

        // Quay lại "Đang làm" → xếp lại; tới giờ thì gửi.
        $order->forceFill(['status' => 'in_progress'])->save();
        $this->travel(4)->minutes();
        $this->assertSame(1, $outbox->processDue());
        Notification::assertSentOnDemand(StageUpdate::class, function (StageUpdate $n, $channels, $notifiable) {
            $mail = $n->toMail($notifiable);
            $html = $n->preview();

            return array_key_first($notifiable->routes['mail']) === 'lan.tran@gmail.com'
                && $mail->from === ['no-reply@net.test', 'Linh Design']
                && $mail->replyTo === [['hoi@linh.vn', 'Linh Design']]
                && str_contains($mail->subject, 'bắt đầu thiết kế')
                && str_contains($html, 'Fraunces')            // mẫu Tạp chí
                && str_contains($html, 'Bắt đầu thiết kế');
        });
        $sent = EmailLog::query()->where('status', 'sent')->firstOrFail();
        $this->assertNotNull($sent->sent_at);
        // Lịch sử đơn có ghi (chỉ freelancer thấy).
        $this->assertTrue($order->events()->where('message', 'like', '%Đã gửi email%')->where('visible', false)->exists());
    }

    public function test_form_order_sends_confirmation_immediately_and_mute_stops_stage_emails(): void
    {
        Notification::fake();
        [$studio] = $this->mailStudio();
        $service = $studio->services()->first();
        $this->post(route('public.order.store', $studio->slug), $this->guard('order-'.$studio->id) + [
            'name' => 'Anh Tú', 'phone' => '0903999888', 'email' => 'tu.nguyen@gmail.com', 'service_id' => $service->id, 'title' => 'Bộ nhận diện', 'brief' => 'Cần gấp',
        ])->assertRedirect();
        $order = Order::query()->latest('id')->first();

        // Gửi ngay sau request (middleware chạy hàng chờ) — chỉ 1 email dù đơn được lưu nhiều lần khi tạo.
        $this->assertSame(1, EmailLog::query()->where('order_id', $order->id)->count());
        app(StageMail::class)->processDue();
        Notification::assertSentOnDemandTimes(StageUpdate::class, 1);
        $this->assertSame('sent', EmailLog::query()->where('order_id', $order->id)->value('status'));

        // Khách tắt email tự động → không xếp nữa.
        $order->forceFill(['reminders_muted' => true])->save();
        $order->forceFill(['status' => 'in_progress'])->save();
        $this->assertSame(1, EmailLog::query()->where('order_id', $order->id)->count());
    }

    public function test_sandbox_redirects_to_studio_and_quota_limits_per_studio(): void
    {
        Notification::fake();
        [$studio, $order] = $this->mailStudio(['sandbox' => true, 'limit' => 1]);
        $outbox = app(StageMail::class);

        $order->forceFill(['status' => 'in_progress'])->save();
        $this->travel(4)->minutes();
        $outbox->processDue();
        Notification::assertSentOnDemand(StageUpdate::class, function (StageUpdate $n, $c, $notifiable) {
            return array_key_first($notifiable->routes['mail']) === 'linh@may.vn' && $n->sandboxFor === 'lan.tran@gmail.com'
                && str_starts_with($n->toMail($notifiable)->subject, '[Thử] ') && str_contains($n->preview(), 'Email thử');
        });
        $this->assertSame('sandbox', EmailLog::query()->where('kind', 'stage:in_progress')->value('status'));

        // Hạn mức 1/ngày đã dùng → email tiếp theo bị bỏ qua, ghi rõ lý do.
        $order->forceFill(['status' => 'review'])->save();
        $this->travel(4)->minutes();
        $outbox->processDue();
        $skipped = EmailLog::query()->where('kind', 'stage:review')->firstOrFail();
        $this->assertSame('skipped', $skipped->status);
        $this->assertStringContainsString('hạn mức', $skipped->note);

        // Studio khác không bị ảnh hưởng bởi hạn mức của studio này.
        $other = $this->makeStudio('studio-khac', ['email' => 'khac@may.vn']);
        $this->assertNull(app(\App\Services\Mail\StudioMailer::class)->blocker($other));
    }

    public function test_manual_send_preview_and_settings_pages(): void
    {
        Notification::fake();
        [$studio, $order] = $this->mailStudio(['stages_on' => false]);
        $order->forceFill(['status' => 'review'])->save();
        $this->assertSame(0, EmailLog::count()); // tự động đang tắt

        $this->actingAs($studio->owner);
        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('Email cho khách')->assertSee('Gửi “Có phương án mới”', false);
        $this->get(route('orders.email.preview', ['order' => $order->id, 'note' => 'Em gửi 3 phương án']))->assertOk()->assertSee('Em gửi 3 phương án');
        $this->post(route('orders.email', $order->id), ['note' => 'Chị xem giúp em nhé'])->assertSessionHas('status');
        Notification::assertSentOnDemand(StageUpdate::class, fn (StageUpdate $n) => $n->note === 'Chị xem giúp em nhé');

        // Không xem/gửi được đơn của studio khác.
        $other = $this->makeStudio('khac');
        $this->actingAs($other->owner)->post(route('orders.email', $order->id))->assertNotFound();
        $this->get(route('orders.email.preview', $order->id))->assertNotFound();

        // Trang cài đặt + xem trước mọi giai đoạn × mọi mẫu giao diện.
        $this->actingAs($studio->owner)->get(route('settings.index'))->assertOk()->assertSee('Email gửi khách')->assertSee('Chế độ thử (sandbox)')->assertSee('Google Analytics');
        foreach (array_keys(config('net.page_themes')) as $theme) {
            foreach (array_keys(config('net.stage_emails.stages')) as $stage) {
                $this->get(route('settings.email.preview', ['loai' => 'stage:'.$stage, 'mau' => $theme]))->assertOk()->assertSee(config('net.stage_emails.stages.'.$stage.'.label'));
            }
            $this->get(route('settings.email.preview', ['loai' => 'reminder:payment', 'mau' => $theme]))->assertOk()->assertSee('Nhắc thanh toán');
        }
        $this->get(route('settings.email.preview', ['loai' => 'bậy']))->assertNotFound();
        $this->get(route('settings.email.log'))->assertOk()->assertSee('Có phương án mới');

        // Lưu cài đặt.
        $this->put(route('settings.email'), ['sender_name' => 'Linh <x>', 'reply_to' => 'linh@may.vn', 'stages_on' => 1, 'stages' => ['review' => ['on' => 1, 'note' => 'Góp ý thoải mái nha']]])->assertSessionHasNoErrors();
        $p = $studio->fresh()->emailPrefs();
        $this->assertTrue($p['stages_on']);
        $this->assertTrue($p['stages']['review']['on']);
        $this->assertFalse($p['stages']['delivered']['on']); // form gửi lên thiếu = tắt
        $this->assertSame('Góp ý thoải mái nha', $p['stages']['review']['note']);
        // Tên người gửi bỏ ký tự phá header.
        $this->assertSame('Linh  x', app(PlatformMail::class)->fromFor($studio->fresh())[1]);

        // Gửi thử tới email studio.
        $this->post(route('settings.email.test'), ['stage' => 'delivered'])->assertSessionHas('status');
        Notification::assertSentOnDemand(StageUpdate::class, fn ($n, $c, $notifiable) => array_key_first($notifiable->routes['mail']) === 'linh@may.vn');
    }

    public function test_reminders_still_work_through_studio_mailer_and_are_logged(): void
    {
        Notification::fake();
        [$studio, $order] = $this->mailStudio(['stages_on' => false]);
        $studio->forceFill(['reminder_prefs' => ['enabled' => true, 'max' => 2, 'kinds' => ['review' => ['on' => true, 'days' => 3]]]])->save();
        $order->forceFill(['status' => 'review'])->save();
        Order::query()->whereKey($order->id)->update(['status_changed_at' => now()->subDays(4)]);

        $this->assertSame(1, app(\App\Services\ReminderService::class)->run());
        Notification::assertSentOnDemand(ClientReminder::class);
        $this->assertSame('reminder:review', EmailLog::query()->where('status', 'sent')->value('kind'));

        // Bị tạm dừng → không gửi nữa.
        $studio->mergeEmailPrefs(['blocked' => true]);
        Order::query()->whereKey($order->id)->update(['status_changed_at' => now()->subDays(10)]);
        $order->reminders()->update(['sent_at' => now()->subDays(5)]);
        $this->assertSame(0, app(\App\Services\ReminderService::class)->run());
    }

    public function test_export_contains_new_settings_but_not_admin_only_fields(): void
    {
        [$studio] = $this->mailStudio(['limit' => 3, 'blocked' => true]);
        $studio->forceFill(['ga_measurement_id' => 'G-TEST1234'])->save();
        $data = app(\App\Services\StudioExporter::class)->export($studio->fresh());
        $this->assertSame('G-TEST1234', $data['studio']['ga_measurement_id']);
        $this->assertArrayNotHasKey('blocked', $data['studio']['email_settings']);
        $this->assertArrayHasKey('emails_sent', $data);
    }
}
