<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Order;
use App\Models\Service;
use App\Notifications\ClientReminder;
use App\Notifications\ConsultationBooked;
use App\Services\OrderService;
use App\Services\ReminderService;
use App\Support\EmailCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/** Nét v6: mẫu brief theo dịch vụ, nhắc khách qua email (lọc email "có vẻ thật"), hẹn gọi tư vấn. */
class V6Test extends NetTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ Lọc email

    public function test_email_check_filters_fake_looking_addresses(): void
    {
        foreach (['ha.nguyen@gmail.com', 'linhdesign+don@gmail.com', 'studio@may.vn', 'contact@company.com.vn', 'mail@studio.vn'] as $ok) {
            $this->assertTrue(EmailCheck::plausible($ok, false), $ok);
        }
        $cases = [
            '' => 'Chưa có email', 'abc' => 'Sai định dạng email', 'a..b@gmail.com' => 'Sai định dạng email',
            'lan@gmial.com' => 'Có vẻ gõ nhầm tên miền', 'lan@gmail.con' => 'Có vẻ gõ nhầm tên miền',
            'khach@example.com' => 'Email mẫu, không phải email thật', 'a@test.com' => 'Email mẫu, không phải email thật',
            'x@mailinator.com' => 'Email dùng một lần (hộp thư tạm)', 'x@sub.yopmail.com' => 'Email dùng một lần (hộp thư tạm)',
            'test@may.vn' => 'Email có vẻ điền cho có', 'aaaa@may.vn' => 'Email có vẻ điền cho có', '123@may.vn' => 'Email có vẻ điền cho có',
            'asdf123@may.vn' => 'Email có vẻ điền cho có', 'lan@gmail.com' => 'Gmail không có tên ngắn dưới 6 ký tự',
        ];
        foreach ($cases as $email => $reason) {
            $r = EmailCheck::check($email, false);
            $this->assertFalse($r['ok'], $email);
            $this->assertSame($reason, $r['reason'], $email);
        }
        $this->assertSame('lan.tran@gmail.com', EmailCheck::check('lan.tran@gmial.com', false)['suggestion']);
    }

    // ------------------------------------------------------------------ Mẫu brief

    public function test_service_brief_template_is_saved_cleaned_and_shown_to_clients(): void
    {
        $studio = $this->makeStudio('mau-brief', ['policy' => null]);
        $logo = $studio->services()->where('name', 'Thiết kế logo')->first();
        $this->actingAs($studio->owner);

        $questions = [
            ['label' => 'Tên thương hiệu', 'type' => 'short', 'required' => true],
            ['label' => 'Kiểu logo', 'type' => 'choice', 'options' => ['Chữ', 'Biểu tượng'], 'required' => true],
            ['label' => 'Dùng ở đâu', 'type' => 'multi', 'options' => "Biển hiệu\nBao bì\n\nBao bì"],
            ['label' => 'Chỉ 1 lựa chọn', 'type' => 'choice', 'options' => ['A']],
            ['label' => '   ', 'type' => 'long'],
            ['label' => '<b>Ghi chú</b>', 'type' => 'kieu-la'],
        ];
        $this->put(route('services.update', $logo->id), ['_service' => $logo->id, 'name' => 'Thiết kế logo', 'is_public' => 1, 'brief_questions' => json_encode($questions)])->assertRedirect();
        $saved = $logo->fresh()->briefQuestions();
        $this->assertCount(5, $saved, 'Bỏ câu trống.');
        $this->assertSame(['Biển hiệu', 'Bao bì'], $saved[2]['options'], 'Lựa chọn trùng / trống bị bỏ.');
        $this->assertSame('short', $saved[3]['type'], 'Câu chọn chỉ 1 lựa chọn → trả lời ngắn.');
        $this->assertSame(['Ghi chú', 'short'], [$saved[4]['label'], $saved[4]['type']]);
        $this->get(route('services.index'))->assertSee('Mẫu brief 5 câu hỏi');

        // Sửa dịch vụ mà không gửi ô mẫu brief → giữ nguyên.
        $logo->refresh()->update(['price' => 5000000]);
        $this->assertCount(5, $logo->fresh()->briefQuestions());

        auth()->logout();
        $this->get(route('public.order', 'mau-brief'))->assertOk()
            ->assertSee('Vài câu hỏi cho thiết kế logo')->assertSee('name="answers['.$logo->id.'][0]"', false)
            ->assertSee('name="answers['.$logo->id.'][2][]"', false);

        $base = ['service_id' => $logo->id, 'title' => 'Logo Gió', 'brief' => 'Tiệm trà', 'name' => 'Gió', 'phone' => '0987000111'];
        $this->post(route('public.order.store', 'mau-brief'), $base + ['answers' => [$logo->id => [0 => '', 1 => 'Bịa ra']]] + $this->guard('order-'.$studio->id))
            ->assertSessionHasErrors(['answers.'.$logo->id.'.0', 'answers.'.$logo->id.'.1']);
        $this->assertSame(0, $studio->orders()->count());

        $this->post(route('public.order.store', 'mau-brief'), $base + ['answers' => [
            $logo->id => [0 => 'Trà Gió', 1 => 'Biểu tượng', 2 => ['Bao bì', 'Không có'], 3 => 'x'],
            999 => [0 => 'của dịch vụ khác'],
        ]] + $this->guard('order-'.$studio->id))->assertRedirect();
        $order = $studio->orders()->first();
        $this->assertSame([
            ['question' => 'Tên thương hiệu', 'answer' => 'Trà Gió'],
            ['question' => 'Kiểu logo', 'answer' => 'Biểu tượng'],
            ['question' => 'Dùng ở đâu', 'answer' => 'Bao bì'],
            ['question' => 'Chỉ 1 lựa chọn', 'answer' => 'x'],
        ], $order->briefAnswers());

        $this->get(route('public.track', $order->tracking_token))->assertSee('Trà Gió')->assertSee('Kiểu logo');
        $this->actingAs($studio->owner)->get(route('orders.show', $order->id))->assertSee('Trả lời mẫu brief')->assertSee('Biểu tượng');
    }

    public function test_presets_are_valid_templates(): void
    {
        foreach (config('net.brief.presets') as $key => $preset) {
            $this->assertSame(count($preset['questions']), count(Service::normalizeQuestions($preset['questions'])), $key);
        }
    }

    // ------------------------------------------------------------------ Nhắc khách

    private function reminderStudio(): array
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'no-reply@net.test']);
        $studio = $this->makeStudio('nhac-khach', ['email' => 'linh@may.vn']);
        $studio->forceFill(['reminder_prefs' => ['enabled' => true, 'max' => 2, 'kinds' => [
            'review' => ['on' => true, 'days' => 3], 'quote' => ['on' => true, 'days' => 2], 'payment' => ['on' => false, 'days' => 3],
        ]]])->save();
        $svc = app(OrderService::class);
        $good = $studio->clients()->create(['name' => 'Chị Lan', 'email' => 'lan.tran@gmail.com']);
        $fake = $studio->clients()->create(['name' => 'Khách thử', 'email' => 'test@test.com']);
        $a = $svc->create($studio, $good, ['title' => 'Logo A', 'brief' => 'B'], 'manual', null, [], 'review');
        $b = $svc->create($studio, $fake, ['title' => 'Logo B', 'brief' => 'B'], 'manual', null, [], 'review');
        Order::query()->whereKey([$a->id, $b->id])->update(['status_changed_at' => now()->subDays(4)]);

        return [$studio->fresh(), $a->fresh(), $b->fresh()];
    }

    public function test_reminders_go_only_to_plausible_emails_with_spacing_and_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00'));
        Notification::fake();
        [$studio, $a, $b] = $this->reminderStudio();
        $service = app(ReminderService::class);

        $this->assertSame(1, $service->run());
        Notification::assertSentOnDemand(ClientReminder::class, fn ($n, $channels, $notifiable) => $n->order->is($a) && $n->kind === 'review'
            && array_key_first($notifiable->routes['mail']) === 'lan.tran@gmail.com');
        $this->assertSame(1, $a->reminders()->count());
        $this->assertSame(0, $b->reminders()->count(), 'Email giả không được gửi.');

        // Chạy lại ngay: chưa đủ 3 ngày từ lần trước.
        $this->assertSame(0, $service->run());
        Carbon::setTestNow(now()->addDays(3)->setTime(10, 0));
        $this->assertSame(1, $service->run());
        Carbon::setTestNow(now()->addDays(3)->setTime(10, 0));
        $this->assertSame(0, $service->run(), 'Tối đa 2 lần cho mỗi mốc.');

        // Mốc mới (khách phản hồi rồi gửi vòng mới) → đếm lại.
        $a->forceFill(['status' => 'revising'])->save();
        $a->forceFill(['status' => 'review'])->save();
        Order::query()->whereKey($a->id)->update(['status_changed_at' => now()->subDays(3)]);
        $this->assertSame(1, $service->run());

        // Ngoài giờ: không gửi.
        Carbon::setTestNow(now()->addDays(5)->setTime(22, 0));
        $this->assertSame(0, $service->run());

        // Ghi lại cho freelancer (khách không thấy).
        $this->assertTrue($a->events()->where('visible', false)->where('message', 'like', '%email nhắc%')->exists());
    }

    public function test_quote_and_mail_not_ready_and_studio_off(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00'));
        Notification::fake();
        [$studio, $a] = $this->reminderStudio();
        Order::query()->whereKey($a->id)->update(['status' => 'quoted', 'quote_sent_at' => now()->subDays(3), 'total_amount' => 1000000]);

        $this->assertSame([[$a->id, 'quote']], collect(app(ReminderService::class)->due($studio))->map(fn ($r) => [$r[0]->id, $r[1]])->all());

        config(['mail.default' => 'log']);
        $this->assertSame(0, app(ReminderService::class)->run(), 'Chưa cấu hình mail thì không gửi.');
        config(['mail.default' => 'smtp']);

        $studio->forceFill(['reminder_prefs' => ['enabled' => false]])->save();
        $this->assertSame(0, app(ReminderService::class)->run());
        Notification::assertNothingSent();
    }

    public function test_client_can_mute_and_designer_can_toggle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00'));
        Notification::fake();
        [$studio, $a] = $this->reminderStudio();

        $this->get(route('public.track.mute', $a->tracking_token))->assertForbidden(); // không có chữ ký
        $this->get(URL::signedRoute('public.track.mute', ['token' => $a->tracking_token]))->assertRedirect(route('public.track', $a->tracking_token));
        $this->assertTrue($a->fresh()->reminders_muted);
        $this->assertSame(0, app(ReminderService::class)->run());

        $this->actingAs($studio->owner)->get(route('orders.show', $a->id))->assertSee('Bật lại nhắc cho đơn này');
        $this->patch(route('orders.reminders', $a->id), ['on' => 1])->assertRedirect();
        $this->assertFalse($a->fresh()->reminders_muted);
        $this->actingAs($this->makeStudio('khac-nhac')->owner)->patch(route('orders.reminders', $a->id), ['on' => 0])->assertNotFound();
    }

    public function test_reminder_email_content_and_settings(): void
    {
        [$studio, $a] = $this->reminderStudio();
        $mail = (new ClientReminder($a->load('client', 'studio'), 'review'))->toMail(null);
        $this->assertStringContainsString('đang chờ bạn xem', $mail->subject);
        $this->assertSame([['linh@may.vn', $studio->name]], $mail->replyTo);
        $this->assertStringContainsString($a->trackingUrl(), $mail->actionUrl);
        $this->assertStringContainsString('khong-nhan-nhac', implode(' ', $mail->outroLines));

        $this->actingAs($studio->owner);
        $this->get(route('settings.index'))->assertOk()->assertSee('Nhắc khách tự động')->assertSee('1/2 khách có email nhận được nhắc');
        $this->put(route('settings.reminders'), ['enabled' => 1, 'max' => 9, 'kinds' => ['review' => ['days' => 3], 'quote' => ['days' => 3], 'payment' => ['days' => 3]]])->assertSessionHasErrors('max');
        $this->put(route('settings.reminders'), ['enabled' => 0, 'max' => 3, 'kinds' => ['review' => ['on' => 1, 'days' => 7], 'quote' => ['on' => 0, 'days' => 3], 'payment' => ['on' => 1, 'days' => 4]]])->assertRedirect();
        $p = $studio->fresh()->reminderPrefs();
        $this->assertFalse($p['enabled']);
        $this->assertSame([3, 7, false], [$p['max'], $p['kinds']['review']['days'], $p['kinds']['quote']['on']]);
    }

    // ------------------------------------------------------------------ Hẹn gọi tư vấn

    private function consultStudio(): \App\Models\Studio
    {
        $studio = $this->makeStudio('hen-goi', ['email' => 'linh@may.vn']);
        $studio->forceFill(['consult_prefs' => [
            'enabled' => true, 'duration' => 30, 'days' => [1, 2, 3, 4, 5], 'ranges' => [['09:00', '10:30']],
            'notice_hours' => 12, 'ahead_days' => 7, 'channels' => ['zalo', 'meet'], 'note' => 'Mình gọi Zalo nha',
        ]])->save();

        return $studio->fresh();
    }

    public function test_consultation_is_off_by_default_and_settings_validate(): void
    {
        $studio = $this->makeStudio('mac-dinh-tat');
        $this->assertFalse($studio->consultEnabled());
        $this->get(route('public.order', 'mac-dinh-tat'))->assertDontSee('Đặt lịch gọi tư vấn');
        $this->post(route('public.consult.store', 'mac-dinh-tat'), [])->assertNotFound();

        $this->actingAs($studio->owner);
        $base = ['duration' => 30, 'notice_hours' => 12, 'ahead_days' => 14, 'channels' => ['zalo']];
        $this->put(route('settings.consult'), $base + ['enabled' => 1, 'days' => [1], 'ranges' => [['10:00', '09:00']]])->assertSessionHasErrors('ranges');
        $this->put(route('settings.consult'), $base + ['enabled' => 1, 'days' => [], 'ranges' => [['09:00', '11:00']]])->assertSessionHasErrors('ranges');
        $this->put(route('settings.consult'), ['enabled' => 1, 'days' => [1, 3], 'ranges' => [['09:00', '11:00'], ['', '']], 'channels' => []] + $base)->assertSessionHasErrors('channels');
        $this->put(route('settings.consult'), $base + ['enabled' => 1, 'days' => [1, 3], 'ranges' => [['09:00', '11:00'], ['', '']]])->assertSessionHasNoErrors()->assertRedirect();
        $p = $studio->fresh()->consultPrefs();
        $this->assertTrue($p['enabled']);
        $this->assertSame([[['09:00', '11:00']], [1, 3]], [$p['ranges'], $p['days']]);
    }

    public function test_slots_respect_days_notice_and_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00')); // Thứ Hai
        $studio = $this->consultStudio();
        $slots = app(\App\Services\ConsultSlots::class)->forStudio($studio);

        // Hôm nay còn < 12 giờ → không có; ngày mai (T3) 09:00, 09:30, 10:00. T7/CN không nhận.
        $this->assertSame('2026-10-27', $slots[0]['date']);
        $this->assertSame(['09:00', '09:30', '10:00'], $slots[0]['times']);
        $this->assertNotContains('2026-11-01', array_column($slots, 'date'));
        $this->assertCount(4, $slots, 'T3–T6 trong 7 ngày (T2 tuần sau nằm ngoài phạm vi).');

        $c = new Consultation(['name' => 'X', 'channel' => 'zalo', 'starts_at' => Carbon::parse('2026-10-27 09:30'), 'duration' => 30]);
        $c->studio_id = $studio->id;
        $c->save();
        $this->assertSame(['09:00', '10:00'], app(\App\Services\ConsultSlots::class)->forStudio($studio)[0]['times']);
    }

    public function test_client_books_cancels_and_studio_manages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00'));
        config(['mail.default' => 'smtp', 'mail.from.address' => 'no-reply@net.test']);
        Notification::fake();
        $studio = $this->consultStudio();

        $this->get(route('public.order', 'hen-goi'))->assertOk()->assertSee('Đặt lịch gọi tư vấn')->assertSee('Mình gọi Zalo nha')->assertSee('Hẹn gọi tư vấn');

        $post = fn (array $d) => $this->post(route('public.consult.store', 'hen-goi'), $d + $this->guard('consult-'.$studio->id));
        $base = ['date' => '2026-10-27', 'time' => '09:00', 'channel' => 'zalo', 'c_name' => 'Anh Nam', 'c_phone' => '0911223344', 'c_email' => 'nam.le@gmail.com', 'c_note' => 'Hỏi về bao bì'];

        $post(['c_phone' => ''] + $base)->assertSessionHasErrors('c_phone', null, 'consult');
        $post(['time' => '09:15'] + $base)->assertSessionHasErrors('slot', null, 'consult');
        $post(['channel' => 'phone'] + $base)->assertSessionHasErrors('channel', null, 'consult');

        $res = $post($base);
        $c = $studio->consultations()->first();
        $res->assertRedirect(route('public.consult.show', $c->token));
        $this->assertSame('2026-10-27 09:00', $c->starts_at->format('Y-m-d H:i'));
        $this->assertSame(30, $c->duration);
        $this->assertNotNull($c->client_id, 'Tạo / gắn hồ sơ khách.');
        Notification::assertSentOnDemand(ConsultationBooked::class, fn ($n) => $n->for === 'client');
        Notification::assertSentOnDemand(ConsultationBooked::class, fn ($n) => $n->for === 'studio');

        // Giờ đó không còn trống; người khác đặt trùng giờ bị từ chối; cùng người đặt thêm bị chặn.
        $post(['c_phone' => '0900000001', 'c_email' => 'khac.nguoi@gmail.com'] + $base)->assertSessionHasErrors('slot', null, 'consult');
        $post(['time' => '10:00'] + $base)->assertSessionHasErrors('slot', null, 'consult');
        $this->assertSame(1, $studio->consultations()->count());

        $this->get(route('public.consult.show', $c->token))->assertOk()->assertSee('Thứ Ba, 27/10 · 09:00–09:30')->assertSee('Google Calendar')
            ->assertSee('noindex', false);
        $ics = $this->get(route('public.consult.ics', $c->token))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString('DTSTART:20261027T020000Z', $ics);
        $this->assertStringContainsString('BEGIN:VALARM', $ics);

        // Freelancer thấy ở Hôm nay + trang Hẹn tư vấn, đánh dấu đã gọi.
        $this->actingAs($studio->owner);
        $this->get(route('today'))->assertSee('Hẹn gọi tư vấn sắp tới')->assertSee('Anh Nam');
        $this->get(route('consultations.index'))->assertOk()->assertSee('Hỏi về bao bì')->assertSee('Tạo đơn');
        $this->patch(route('consultations.update', $c->id), ['status' => 'done'])->assertRedirect();
        $this->assertSame('done', $c->fresh()->status);
        $this->actingAs($this->makeStudio('studio-la')->owner)->patch(route('consultations.update', $c->id), ['status' => 'cancelled'])->assertNotFound();

        // Khách huỷ (chỉ khi còn sắp tới).
        $c->refresh()->forceFill(['status' => 'booked'])->save();
        auth()->logout();
        $this->post(route('public.consult.cancel', $c->token))->assertRedirect();
        $this->assertSame('cancelled', $c->fresh()->status);
        $this->assertContains('09:00', app(\App\Services\ConsultSlots::class)->forStudio($studio)[0]['times'], 'Huỷ thì giờ trống lại.');
    }
}
