<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Studio;
use App\Services\Ai\Gemini;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Trợ lý AI (Gemini) — mọi lượt gọi Google đều được giả lập bằng Http::fake. */
class AiAssistantTest extends NetTestCase
{
    private const KEY = 'AIzaSyTestKey_1234567890abcdefXYZ';

    /** Http::fake() gọi lần 2 KHÔNG thay được stub cũ (stub đăng ký trước khớp trước) → mỗi lần giả lập dùng 1 factory mới. */
    private function fake(?array $stubs = null): void
    {
        Http::swap(new HttpFactory(app('events')));
        Http::fake($stubs);
    }

    private function aiStudio(string $slug = 'ai-studio', array $attrs = []): Studio
    {
        $studio = $this->makeStudio($slug, $attrs);
        $studio->forceFill(['gemini_api_key' => self::KEY])->save();

        return $studio->fresh();
    }

    private static function reply(array|string $payload): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload]]]]]];
    }

    private static function quotaError(string $quotaId = 'GenerateRequestsPerDayPerProjectPerModel-FreeTier'): array
    {
        return ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'You exceeded your current quota.', 'details' => [
            ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => $quotaId]]],
            ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '30s'],
        ]]];
    }

    private static function url(string $model): string
    {
        return 'generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
    }

    // ================= Cài đặt key =================

    public function test_save_verify_and_remove_key(): void
    {
        $studio = $this->makeStudio('cai-key');
        $this->actingAs($studio->owner);
        $this->get(route('settings.index'))->assertOk()->assertSee('Trợ lý AI')->assertSee('aistudio.google.com/apikey');

        // Key sai → Google trả 400 API_KEY_INVALID → không lưu.
        $this->fake(['*/v1beta/models*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid. Please pass a valid API key.',
            'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'API_KEY_INVALID']]]], 400)]);
        $this->put(route('settings.ai'), ['gemini_api_key' => 'AIzaSyWrongKey_0000000000000000'])->assertSessionHasErrors('gemini_api_key');
        $this->assertFalse($studio->fresh()->hasAi());

        // Key đúng → lưu MÃ HOÁ.
        $this->fake(['*/v1beta/models*' => Http::response(['models' => [['name' => 'models/gemini-3.5-flash']]], 200)]);
        $this->put(route('settings.ai'), ['gemini_api_key' => ' '.self::KEY.' '])->assertRedirect();
        $studio->refresh();
        $this->assertSame(self::KEY, $studio->geminiKey());
        $raw = DB::table('studios')->where('id', $studio->id)->value('gemini_api_key');
        $this->assertNotSame(self::KEY, $raw);
        $this->assertStringNotContainsString(self::KEY, $raw);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('x-goog-api-key', self::KEY) && ! str_contains($r->url(), self::KEY));

        $page = $this->get(route('settings.index'))->assertOk()->assertSee('Đang bật')->assertSee('Gemini 3.5 Flash')->assertSee('Gemini 3.1 Flash Lite');
        $page->assertDontSee(self::KEY);

        // Key không lộ ra file xuất dữ liệu.
        $this->assertStringNotContainsString(self::KEY, $this->get(route('settings.export'))->streamedContent());
        $this->assertArrayNotHasKey('gemini_api_key', $studio->toArray());

        $this->delete(route('settings.ai.remove'))->assertRedirect();
        $this->assertFalse($studio->fresh()->hasAi());
    }

    public function test_unreadable_key_after_app_key_change_does_not_crash(): void
    {
        $studio = $this->makeStudio('doi-app-key');
        DB::table('studios')->where('id', $studio->id)->update(['gemini_api_key' => 'khong-phai-chuoi-ma-hoa']);
        $studio->refresh();

        $this->assertNull($studio->geminiKey());
        $this->actingAs($studio->owner)->get(route('settings.index'))->assertOk()->assertSee('Chưa bật');

        $this->fake(['*/v1beta/models*' => Http::response(['models' => []], 200)]);
        $this->put(route('settings.ai'), ['gemini_api_key' => self::KEY])->assertRedirect();
        $this->assertSame(self::KEY, $studio->fresh()->geminiKey());
    }

    public function test_key_format_is_not_over_restricted(): void
    {
        $studio = $this->makeStudio('key-dau-cham');
        $this->actingAs($studio->owner);
        $this->fake(['*/v1beta/models*' => Http::response(['models' => [
            ['name' => 'models/gemini-3.6-flash', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
            ['name' => 'models/gemini-3.5-flash-lite', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-3.8-flash-tts', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-embedding-2', 'supportedGenerationMethods' => ['embedContent']],
        ]], 200)]);

        // Key có dấu chấm (định dạng mới của Google) phải lưu được.
        $dotted = 'AQ.Ab8RN6LabcDEF-ghi_jkl.1234567890';
        $this->put(route('settings.ai'), ['gemini_api_key' => $dotted])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($dotted, $studio->fresh()->geminiKey());

        // Danh sách mô hình key gọi được được nhớ lại để gợi ý (bỏ bản giọng nói / nhúng).
        $this->assertSame(['gemini-3.5-flash-lite', 'gemini-3.6-flash'], app(Gemini::class)->cachedModels($studio->fresh()));
        $this->get(route('settings.index'))->assertSee('<option value="gemini-3.6-flash">', false)->assertDontSee('gemini-3.8-flash-tts');

        // Ký tự tiếng Việt thì chặn ngay, không gọi Google.
        $this->fake();
        $this->put(route('settings.ai'), ['gemini_api_key' => 'AIzaSyKhóaCóDấuTiếngViệt123456'])->assertSessionHasErrors('gemini_api_key');
        Http::assertNothingSent();
    }

    public function test_studio_can_choose_models_and_reset_to_default(): void
    {
        $studio = $this->aiStudio('chon-mo-hinh');
        $this->actingAs($studio->owner);
        $this->get(route('settings.index'))->assertSee('Mô hình AI')->assertSee('Đang dùng mặc định của hệ thống');
        $this->assertSame(config('net.ai.default_models'), $studio->aiModelIds());

        // Tên không hợp lệ / để trống hết → lỗi.
        $this->put(route('settings.ai.models'), ['models' => ['gemini 3.6 flash!', '', '']])->assertSessionHasErrors('models.0');
        $this->put(route('settings.ai.models'), ['models' => ['', '', '']])->assertSessionHasErrors('models.0');

        // Lưu chuỗi riêng (chuẩn hoá "models/…", chữ hoa, bỏ trùng). Key không thấy mô hình → chỉ nhắc, vẫn lưu.
        $this->fake(['*/v1beta/models*' => Http::response(['models' => [['name' => 'models/gemini-3.6-flash', 'supportedGenerationMethods' => ['generateContent']]]], 200)]);
        $this->put(route('settings.ai.models'), ['models' => ['models/Gemini-3.6-Flash', 'gemini-9-flash-lite', 'gemini-3.6-flash']])
            ->assertRedirect()->assertSessionHas('warning', fn ($w) => str_contains($w, 'gemini-9-flash-lite'));
        $studio->refresh();
        $this->assertSame(['gemini-3.6-flash', 'gemini-9-flash-lite'], $studio->aiModelIds());
        $this->assertFalse($studio->usesDefaultAiModels());
        $this->get(route('settings.index'))->assertSee('Bạn đã tự chọn')->assertSee('Gemini 3.6 Flash')->assertSee('Gemini 9 Flash Lite');

        // Gọi AI dùng đúng chuỗi đã chọn.
        $this->fake([self::url('gemini-3.6-flash') => Http::response(self::reply(['title' => 'Từ 3.6', 'brief' => 'B', 'priority' => 'normal', 'stage' => 'new', 'links' => [], 'missing' => []]))]);
        $this->post(route('orders.ai-draft'), ['ai_text' => 'logo cho tiệm nail'])->assertSessionHasInput('title', 'Từ 3.6');
        $this->assertSame('Gemini 3.6 Flash', session('ai_draft')['model']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'models/gemini-3.6-flash:generateContent'));
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'gemini-3.5-flash'));

        // Về mặc định.
        $this->put(route('settings.ai.models'), ['reset' => 1, 'models' => ['x']])->assertRedirect();
        $this->assertTrue($studio->fresh()->usesDefaultAiModels());
        $this->assertSame(config('net.ai.default_models'), $studio->fresh()->aiModelIds());
    }

    // ================= Lên đơn bằng ngôn ngữ tự nhiên =================

    public function test_draft_order_from_chat_fills_form_and_validates_ai_output(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Ho_Chi_Minh'));
        $studio = $this->aiStudio('len-don');
        $other = $this->makeStudio('studio-la');
        $logo = $studio->services()->first();

        $this->fake([self::url('gemini-3.5-flash') => Http::response(self::reply([
            'client_name' => 'Chị Lan', 'client_phone' => '0912 345 678', 'client_email' => null, 'client_facebook' => 'fb.com/lan.bakery',
            'title' => 'Logo tiệm bánh Mây', 'brief' => "- Logo cho tiệm bánh nhỏ\n- Tông kem nâu, ấm áp", 'style' => 'Dễ thương, vẽ tay', 'colors' => 'Kem, nâu',
            'specs' => null, 'copy_text' => 'Mây Bakery', 'budget_note' => null,
            'service_id' => $logo->id, 'price' => 1500000, 'deposit_percent' => 50, 'revision_rounds' => null,
            'due_on' => '2026-10-23', 'client_due_on' => '2020-01-01', 'priority' => 'normal', 'stage' => 'in_progress',
            'links' => ['https://drive.google.com/drive/folders/abc', 'javascript:alert(1)', 'not a url'],
            'internal_note' => 'Khách hay trả lời buổi tối', 'missing' => ['Kích thước / nơi sử dụng logo'],
        ]))]);

        $this->actingAs($studio->owner);
        $this->get(route('orders.create'))->assertOk()->assertSee('Lên đơn nhanh bằng AI');

        $this->post(route('orders.ai-draft'), ['ai_text' => 'Chị Lan 0912345678 fb.com/lan.bakery cần logo tiệm bánh Mây, 1tr5 cọc 50%, thứ 6 tuần sau giao'])
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasInput('title', 'Logo tiệm bánh Mây')
            ->assertSessionHasInput('client_name', 'Chị Lan')
            ->assertSessionHasInput('client_phone', '0912345678')
            ->assertSessionHasInput('client_facebook', 'https://www.facebook.com/lan.bakery')
            ->assertSessionHasInput('service_id', $logo->id)
            ->assertSessionHasInput('price', '1500000')
            ->assertSessionHasInput('deposit_percent', 50)
            ->assertSessionHasInput('due_on', '2026-10-23')
            ->assertSessionHasInput('status', 'in_progress')
            ->assertSessionHas('ai_draft');

        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('client_due_on', $old, 'Ngày trong quá khứ phải bị bỏ.');
        $this->assertSame([['url' => 'https://drive.google.com/drive/folders/abc', 'label' => '']], $old['links']);

        // Trang form hiện bản nháp + danh sách còn thiếu.
        $this->get(route('orders.create'))->assertOk()->assertSee('AI đã điền sẵn')->assertSee('Kích thước / nơi sử dụng logo')->assertSee('Logo tiệm bánh Mây');

        // Prompt gửi đi có danh sách dịch vụ, ngày hôm nay, lược đồ JSON; key nằm ở header.
        Http::assertSent(function (HttpRequest $r) use ($logo) {
            $body = $r->data();

            return $r->hasHeader('x-goog-api-key', self::KEY)
                && str_contains($body['systemInstruction']['parts'][0]['text'], 'id '.$logo->id.': '.$logo->name)
                && str_contains($body['systemInstruction']['parts'][0]['text'], '2026-10-12')
                && ($body['generationConfig']['responseMimeType'] ?? null) === 'application/json'
                && isset($body['generationConfig']['responseSchema']['properties']['title']);
        });

        // Dịch vụ của studio khác do AI "bịa" ra không được chọn.
        $this->fake([self::url('gemini-3.5-flash') => Http::response(self::reply(['title' => 'X', 'brief' => 'Y', 'service_id' => $other->services()->first()->id, 'priority' => 'urgent', 'stage' => 'new', 'links' => [], 'missing' => []]))]);
        $this->post(route('orders.ai-draft'), ['ai_text' => 'đơn gấp cho khách mới nha'])->assertRedirect()->assertSessionHasInput('priority', 'urgent');
        $this->assertArrayNotHasKey('service_id', session()->getOldInput());

        Carbon::setTestNow();
    }

    public function test_draft_order_picks_existing_client_and_creates_order_with_agreed_price(): void
    {
        $studio = $this->aiStudio('khach-cu');
        $client = $studio->clients()->create(['name' => 'Anh Tuấn', 'facebook' => 'https://www.facebook.com/tuan.coffee']);

        $this->fake([self::url('gemini-3.5-flash') => Http::response(self::reply([
            'client_name' => 'Tuấn', 'client_facebook' => 'https://m.facebook.com/tuan.coffee', 'title' => 'Menu quán cà phê', 'brief' => 'Menu A3 2 mặt',
            'price' => 800000, 'priority' => 'normal', 'stage' => 'in_progress', 'links' => [], 'missing' => [],
        ]))]);

        $this->actingAs($studio->owner);
        $this->post(route('orders.ai-draft'), ['ai_text' => 'anh Tuấn fb tuan.coffee làm menu A3 800k'])
            ->assertSessionHasInput('client_id', $client->id)
            ->assertSessionMissing('errors');
        $this->get(route('orders.create'))->assertSee('Anh Tuấn (trùng Facebook)');

        // Freelancer bấm "Tạo đơn" với dữ liệu đã điền sẵn → giá chốt thành báo giá.
        $input = session()->getOldInput();
        unset($input['ai_text']);
        $this->post(route('orders.store'), $input)->assertRedirect();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame($client->id, $order->client_id);
        $this->assertEquals(800000, (float) $order->total_amount);
        $this->assertSame('Phí thiết kế', $order->charges()->first()->label);
    }

    public function test_agreed_price_accepts_vietnamese_formatting_and_overrides_service_price(): void
    {
        $studio = $this->makeStudio('gia-chot');
        $service = $studio->services()->first();
        $service->update(['price' => 2000000]);
        $this->actingAs($studio->owner);

        $this->post(route('orders.store'), [
            'client_name' => 'Hà', 'client_phone' => '0909000111', 'service_id' => $service->id, 'price' => '1.750.000 đ',
            'title' => 'Banner', 'brief' => 'Banner FB', 'priority' => 'normal', 'revision_rounds' => 2, 'deposit_percent' => 0,
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertEquals(1750000, (float) $order->total_amount);
        $this->assertSame($service->name, $order->charges()->first()->label);

        // Sửa đơn không được nhét "price" vào.
        $this->put(route('orders.update', $order->id), ['client_id' => $order->client_id, 'title' => 'Banner', 'brief' => 'B', 'priority' => 'normal', 'revision_rounds' => 2, 'deposit_percent' => 0, 'price' => 1])
            ->assertSessionHasErrors('price');
    }

    // ================= Tự chuyển model =================

    public function test_falls_back_when_smart_model_quota_is_used_up(): void
    {
        $studio = $this->aiStudio('fallback');
        $this->fake([
            self::url('gemini-3.5-flash') => Http::response(self::quotaError(), 429),
            self::url('gemini-3.5-flash-lite') => Http::response(self::reply(['title' => 'Từ Lite', 'brief' => 'B', 'priority' => 'normal', 'stage' => 'new', 'links' => [], 'missing' => []])),
        ]);

        $this->actingAs($studio->owner);
        $this->post(route('orders.ai-draft'), ['ai_text' => 'logo cho tiệm trà sữa'])->assertSessionHasInput('title', 'Từ Lite');
        $this->assertSame('Gemini 3.5 Flash Lite', session('ai_draft')['model']);

        // Lần sau không gọi lại model đã hết lượt ngày.
        $this->post(route('orders.ai-draft'), ['ai_text' => 'logo cho tiệm trà chanh']);
        Http::assertSentCount(3); // flash (429) → lite, rồi lite thẳng

        $usage = collect(app(Gemini::class)->usage($studio))->keyBy('id');
        $this->assertTrue($usage['gemini-3.5-flash']['exhausted']);
        $this->assertSame(2, $usage['gemini-3.5-flash-lite']['used']);
        $this->get(route('settings.index'))->assertSee('hết lượt hôm nay');
    }

    public function test_retired_model_and_server_errors_are_skipped(): void
    {
        $studio = $this->aiStudio('model-cu');
        $this->fake([
            self::url('gemini-3.5-flash') => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'models/gemini-3.5-flash is not found']], 404),
            self::url('gemini-3.5-flash-lite') => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'The model is overloaded.']], 503),
            self::url('gemini-3.1-flash-lite') => Http::response(self::reply(['title' => 'Từ 3.1', 'brief' => 'B', 'priority' => 'normal', 'stage' => 'new', 'links' => [], 'missing' => []])),
        ]);

        $this->actingAs($studio->owner);
        $this->post(route('orders.ai-draft'), ['ai_text' => 'thiết kế standee khai trương'])->assertSessionHasInput('title', 'Từ 3.1');
    }

    public function test_all_models_exhausted_or_bad_key_gives_clear_message(): void
    {
        $studio = $this->aiStudio('het-luot');
        $this->fake(['*:generateContent' => Http::response(self::quotaError(), 429)]);

        $this->actingAs($studio->owner);
        $this->post(route('orders.ai-draft'), ['ai_text' => 'poster sự kiện âm nhạc'])
            ->assertSessionHasErrors('ai_text')
            ->assertSessionHasInput('ai_text', 'poster sự kiện âm nhạc');
        $this->assertStringContainsString('hết lượt', session('errors')->first('ai_text'));

        // Hết lượt PHÚT chỉ tạm dừng ngắn, không đánh dấu hết lượt cả ngày.
        $studio2 = $this->aiStudio('het-phut');
        $this->fake(['*:generateContent' => Http::response(self::quotaError('GenerateRequestsPerMinutePerProjectPerModel-FreeTier'), 429)]);
        $this->actingAs($studio2->owner)->post(route('orders.ai-draft'), ['ai_text' => 'poster sự kiện âm nhạc'])->assertSessionHasErrors('ai_text');
        $this->assertStringContainsString('1 phút', session('errors')->first('ai_text'));
        $this->assertFalse(collect(app(Gemini::class)->usage($studio2))->contains('exhausted', true));

        // Key bị thu hồi → dừng ngay, không thử model khác.
        $studio3 = $this->aiStudio('key-hong');
        $this->fake(['*:generateContent' => Http::response(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Permission denied']], 403)]);
        $this->actingAs($studio3->owner)->post(route('orders.ai-draft'), ['ai_text' => 'poster sự kiện âm nhạc'])->assertSessionHasErrors('ai_text');
        $this->assertStringContainsString('không hợp lệ', session('errors')->first('ai_text'));
        Http::assertSentCount(1);
    }

    public function test_without_key_ai_is_offered_but_not_called(): void
    {
        $this->fake();
        $studio = $this->makeStudio('chua-co-key');
        $this->actingAs($studio->owner);

        $this->get(route('orders.create'))->assertOk()->assertSee('Bật Trợ lý AI miễn phí')->assertDontSee('Lên đơn bằng AI');
        $this->post(route('orders.ai-draft'), ['ai_text' => 'logo cho tiệm hoa'])->assertSessionHasErrors('ai_text');
        Http::assertNothingSent();
    }

    // ================= Trợ lý trong trang đơn =================

    public function test_order_page_assistant_message_brief_and_feedback(): void
    {
        $studio = $this->aiStudio('trang-don', ['bank_code' => 'MB', 'bank_account' => '123456']);
        $client = $studio->clients()->create(['name' => 'Chị Mai', 'phone' => '0908000111']);
        $order = app(\App\Services\OrderService::class)->create($studio, $client, ['title' => 'Logo spa', 'brief' => 'Logo cho spa'], 'manual', null, [], 'in_progress');
        $this->actingAs($studio->owner);

        $this->get(route('orders.show', $order->id))->assertOk()->assertSee('Trợ lý AI')->assertSee('Brief còn thiếu gì?')->assertDontSee('việc cần sửa');

        // Soạn tin nhắn.
        $this->fake([self::url('gemini-3.5-flash') => Http::response(self::reply('Chào chị Mai, mình đang làm logo spa, dự kiến gửi chị bản đầu tiên vào thứ 5 nhé.'))]);
        $this->post(route('orders.ai', $order->id), ['task' => 'message', 'situation' => 'progress', 'note' => 'gửi bản đầu thứ 5'])
            ->assertRedirect(route('orders.show', $order->id).'#tro-ly-ai')->assertSessionHas('ai_result');
        $this->get(route('orders.show', $order->id))->assertSee('Chào chị Mai, mình đang làm logo spa');
        Http::assertSent(fn (HttpRequest $r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $order->trackingUrl())
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'gửi bản đầu thứ 5')
            && ! isset($r->data()['generationConfig']));

        // "Theo ý mình" bắt buộc ghi chú.
        $this->post(route('orders.ai', $order->id), ['task' => 'message', 'situation' => 'custom'])->assertSessionHasErrors('note');

        // Rà brief.
        $this->fake([self::url('gemini-3.5-flash') => Http::response(self::reply(['missing' => ['Logo dùng ở đâu (biển hiệu, mạng xã hội)?', 'Tên spa chính xác'], 'message' => "Chị ơi cho mình hỏi thêm:\n1. Logo dùng ở đâu?\n2. Tên spa chính xác?"]))]);
        $this->post(route('orders.ai', $order->id), ['task' => 'brief'])->assertSessionHas('ai_result');
        $this->get(route('orders.show', $order->id))->assertSee('Tên spa chính xác')->assertSee('Tin nhắn hỏi khách');

        // Chưa có góp ý → báo rõ, không gọi Google.
        $this->fake();
        $this->post(route('orders.ai', $order->id), ['task' => 'feedback'])->assertSessionHas('ai_error');
        Http::assertNothingSent();

        // Có vòng khách góp ý → gom thành việc cần sửa.
        $round = $order->rounds()->create(['number' => 1, 'status' => 'changes', 'sent_at' => now(), 'responded_at' => now()]);
        $order->feedback()->create(['round_id' => $round->id, 'author' => 'client', 'body' => 'Màu hơi tối, chữ nhỏ quá']);
        $this->get(route('orders.show', $order->id))->assertSee('Góp ý vòng V1');
        $this->fake([self::url('gemini-3.5-flash') => Http::response(self::reply(['tasks' => ['Làm sáng màu nền', 'Tăng cỡ chữ tên spa'], 'summary' => 'Khách ưng bố cục, muốn sáng và rõ chữ hơn.']))]);
        $this->post(route('orders.ai', $order->id), ['task' => 'feedback'])->assertSessionHas('ai_result');
        $this->get(route('orders.show', $order->id))->assertSee('Tăng cỡ chữ tên spa')->assertSee('☐ Làm sáng màu nền');
        Http::assertSent(fn (HttpRequest $r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Màu hơi tối, chữ nhỏ quá'));

        // Không đọc được đơn của studio khác.
        $other = $this->aiStudio('khac-studio');
        $this->actingAs($other->owner)->post(route('orders.ai', $order->id), ['task' => 'brief'])->assertNotFound();
    }

    public function test_ai_routes_are_rate_limited(): void
    {
        $studio = $this->aiStudio('gioi-han');
        $this->fake(['*:generateContent' => Http::response(self::reply(['title' => 'T', 'brief' => 'B', 'priority' => 'normal', 'stage' => 'new', 'links' => [], 'missing' => []]))]);
        $this->actingAs($studio->owner);

        for ($i = 0; $i < 8; $i++) {
            $this->post(route('orders.ai-draft'), ['ai_text' => 'đơn thử số '.$i.' cho khách'])->assertRedirect();
        }
        $this->post(route('orders.ai-draft'), ['ai_text' => 'đơn thử số 9 cho khách'])->assertStatus(429);
    }
}
