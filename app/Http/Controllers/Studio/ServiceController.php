<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\PriceListImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Dịch vụ & bảng giá — hiện trên trang đặt đơn công khai, dùng làm dòng báo giá mặc định khi tạo đơn. */
class ServiceController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request): View
    {
        $studio = $this->studio($request);

        $services = $studio->services()->withCount('orders')->orderBy('sort_order')->orderBy('id')->get();

        return view('services.index', [
            'studio' => $studio,
            'services' => $services,
            // Nút "Tải ảnh về / Sao chép": chỉ hiện khi có dịch vụ công khai để vẽ và máy chủ vẽ được (GD + font).
            'canPriceImage' => $services->contains('is_public', true) && app(PriceListImage::class)->available(),
            'priceImageTheme' => config('net.page_themes.'.$studio->pageTheme().'.label'),
        ]);
    }

    /**
     * Ảnh bảng giá (PNG) vẽ từ các dịch vụ công khai, theo mẫu giao diện + màu nhấn của studio.
     * ?tai=1 → tải về (Content-Disposition: attachment), không có → hiện/sao chép trực tiếp.
     */
    public function priceImage(Request $request, PriceListImage $images): Response
    {
        $studio = $this->studio($request);

        if (! $images->available()) {
            return response('Máy chủ chưa vẽ được ảnh (thiếu GD/FreeType hoặc font).', 503, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $png = $images->render($studio);
        if ($png === null) {
            return response('Chưa có dịch vụ công khai nào để vẽ bảng giá.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $headers = [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];
        if ($request->boolean('tai')) {
            $headers['Content-Disposition'] = 'attachment; filename="bang-gia-'.$studio->slug.'.png"';
        }

        return response($png, 200, $headers);
    }

    public function store(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        $studio->services()->create($this->validated($request) + ['sort_order' => (int) $studio->services()->max('sort_order') + 1]);

        return back()->with('status', 'Đã thêm dịch vụ.');
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $studio = $this->studio($request);
        $studio->services()->findOrFail($service)->update($this->validated($request));

        return back()->with('status', 'Đã lưu dịch vụ.');
    }

    /** Xoá mềm: đơn cũ vẫn giữ tên dịch vụ. */
    public function destroy(Request $request, int $service): RedirectResponse
    {
        $studio = $this->studio($request);
        $studio->services()->findOrFail($service)->delete();

        return back()->with('status', 'Đã xoá dịch vụ khỏi bảng giá.');
    }

    public function moveUp(Request $request, int $service): RedirectResponse
    {
        $studio = $this->studio($request);
        $list = $studio->services()->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $list->search(fn ($s) => $s->id === $service);
        abort_if($index === false, 404);

        if ($index > 0) {
            [$list[$index - 1], $list[$index]] = [$list[$index], $list[$index - 1]];
        }
        foreach ($list as $i => $s) {
            if ($s->sort_order !== $i) {
                $s->forceFill(['sort_order' => $i])->save();
            }
        }

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0|max:10000000000',
            'price_is_from' => 'nullable|boolean',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'revision_rounds' => 'nullable|integer|min:0|max:20',
            'deposit_percent' => 'nullable|integer|min:0|max:100',
            'brief_hint' => 'nullable|string|max:2000',
            'brief_questions' => 'nullable|json|max:30000',
            'is_public' => 'nullable|boolean',
        ], ['brief_questions.json' => 'Mẫu brief không đọc được — tải lại trang rồi thử lại.']);

        $data['price'] = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
        $data['price_is_from'] = (bool) ($data['price_is_from'] ?? false);
        $data['is_public'] = (bool) ($data['is_public'] ?? false);
        // Mẫu brief gửi lên dạng JSON (trình soạn câu hỏi bằng Alpine) → làm sạch trước khi lưu.
        if (! $request->has('brief_questions')) {
            unset($data['brief_questions']);

            return $data;
        }
        $questions = Service::normalizeQuestions((array) json_decode((string) ($data['brief_questions'] ?? '[]'), true));
        $data['brief_questions'] = $questions ?: null;

        return $data;
    }
}
