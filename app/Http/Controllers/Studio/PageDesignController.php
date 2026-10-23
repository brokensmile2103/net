<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use App\Services\ImageService;
use App\Support\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Cài đặt → Trang đặt đơn: mẫu giao diện, màu nhấn (có sẵn hoặc mã HEX riêng), câu chào, ảnh bìa, tác phẩm tiêu biểu. */
class PageDesignController extends Controller
{
    use ResolvesStudio;

    public function update(Request $request): RedirectResponse
    {
        $studio = $this->studio($request);
        if ($request->filled('accent_custom')) {
            $request->merge(['accent_custom' => Color::normalize($request->input('accent_custom')) ?? $request->input('accent_custom')]);
        }

        $data = $request->validate([
            'page_theme' => ['sometimes', 'required', Rule::in(array_keys(config('net.page_themes')))],
            'accent_color' => ['required', Rule::in([...array_keys(config('net.accent_colors')), 'custom'])],
            'accent_custom' => ['required_if:accent_color,custom', 'nullable', 'regex:/^#[0-9a-f]{6}$/', function ($attr, $value, $fail) {
                // Gần trắng thì nút bấm, viền, nền nhạt đều biến mất trên nền giấy.
                if (is_string($value) && preg_match('/^#[0-9a-f]{6}$/', $value) && Color::luminance($value) > 0.85) {
                    $fail('Màu này quá nhạt — chữ và nút sẽ khó thấy trên nền trang. Chọn màu đậm hơn một chút nhé.');
                }
            }],
            'page_headline' => 'nullable|string|max:160',
        ], [
            'accent_custom.required_if' => 'Chọn hoặc nhập mã màu (VD: #ff5a1f).',
            'accent_custom.regex' => 'Mã màu dạng #RRGGBB, VD: #ff5a1f.',
        ]);

        $studio->update([
            'page_theme' => $data['page_theme'] ?? $studio->pageTheme(),
            'accent_color' => $data['accent_color'],
            // Giữ lại mã HEX đã nhập kể cả khi tạm chọn màu có sẵn — quay lại "Màu riêng" không phải gõ lại.
            'accent_custom' => $data['accent_custom'] ?? $studio->accent_custom,
            'page_headline' => $request->has('page_headline') ? ($data['page_headline'] ?? null) : $studio->page_headline,
        ]);

        return redirect()->to(route('settings.index').'#trang-dat-don')->with('status', 'Đã lưu giao diện trang đặt đơn.');
    }

    public function cover(Request $request, ImageService $images): RedirectResponse
    {
        $studio = $this->studio($request);
        $back = redirect()->to(route('settings.index').'#trang-dat-don');

        if ($request->boolean('remove')) {
            $images->delete($studio, $studio->cover_path);
            $studio->forceFill(['cover_path' => null])->save();

            return $back->with('status', 'Đã gỡ ảnh bìa trang đặt đơn.');
        }

        $request->validate(['cover' => 'required|image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb')],
            ['cover.required' => 'Chọn ảnh bìa.', 'cover.image' => 'File phải là ảnh JPG, PNG hoặc WebP.']);
        $stored = $images->store($request->file('cover'), $studio, 'page', (int) config('net.portfolio.cover_max_px', 2000));
        $images->delete($studio, $studio->cover_path);
        $studio->forceFill(['cover_path' => $stored['path']])->save();

        return $back->with('status', 'Đã cập nhật ảnh bìa trang đặt đơn.');
    }

    public function addWork(Request $request, ImageService $images): RedirectResponse
    {
        $studio = $this->studio($request);
        $max = (int) config('net.portfolio.max_items', 9);
        $left = $max - $studio->portfolioItems()->count();

        $request->validate([
            'works' => 'required|array|min:1|max:'.max(1, $left),
            'works.*' => 'image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb'),
        ], [
            'works.required' => 'Chọn ít nhất 1 ảnh tác phẩm.',
            'works.max' => $left > 0 ? 'Chỉ thêm được '.$left.' ảnh nữa (tối đa '.$max.' tác phẩm).' : 'Đã đủ '.$max.' tác phẩm — xoá bớt rồi thêm ảnh mới.',
            'works.*.image' => 'Có file không phải ảnh JPG, PNG hoặc WebP.',
        ]);
        if ($left <= 0) {
            return back()->withErrors(['works' => 'Đã đủ '.$max.' tác phẩm — xoá bớt rồi thêm ảnh mới.'])->withFragment('trang-dat-don');
        }

        $order = (int) $studio->portfolioItems()->max('sort_order');
        foreach ($request->file('works') as $file) {
            $stored = $images->store($file, $studio, 'portfolio', (int) config('net.portfolio.max_px', 1600));
            $item = new PortfolioItem(['sort_order' => ++$order]);
            $item->forceFill(['studio_id' => $studio->id] + $stored)->save();
        }

        return redirect()->to(route('settings.index').'#trang-dat-don')->with('status', 'Đã thêm '.count($request->file('works')).' tác phẩm.');
    }

    public function updateWork(Request $request, int $item): RedirectResponse
    {
        $work = $this->work($request, $item);
        $work->update($request->validate(['caption' => 'nullable|string|max:120']));

        return redirect()->to(route('settings.index').'#trang-dat-don')->with('status', 'Đã lưu chú thích.');
    }

    /** Đưa tác phẩm lên trước 1 bậc (đổi chỗ với tác phẩm đứng trước). */
    public function moveWork(Request $request, int $item): RedirectResponse
    {
        $work = $this->work($request, $item);
        $items = $work->studio->portfolioItems()->get()->values();
        $index = $items->search(fn ($i) => $i->id === $work->id);

        if ($index > 0) {
            DB::transaction(function () use ($items, $index) {
                // Đánh số lại liền mạch rồi mới đổi chỗ — tránh 2 ảnh trùng sort_order.
                foreach ($items as $i => $it) {
                    $it->sort_order = $i;
                }
                [$items[$index - 1]->sort_order, $items[$index]->sort_order] = [$index, $index - 1];
                $items->each->save();
            });
        }

        return redirect()->to(route('settings.index').'#trang-dat-don');
    }

    public function removeWork(Request $request, ImageService $images, int $item): RedirectResponse
    {
        $work = $this->work($request, $item);
        $images->delete($work->studio, $work->path, $work->bytes);
        $work->delete();

        return redirect()->to(route('settings.index').'#trang-dat-don')->with('status', 'Đã xoá tác phẩm.');
    }

    private function work(Request $request, int $item): PortfolioItem
    {
        return PortfolioItem::query()->where('studio_id', $this->studio($request)->id)->findOrFail($item);
    }
}
