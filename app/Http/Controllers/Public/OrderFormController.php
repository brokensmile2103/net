<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Services\ClientDirectory;
use App\Services\FormGuard;
use App\Services\OrderService;
use App\Services\ShareImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Trang đặt đơn công khai của 1 studio (/dat/{slug}). Khách không cần tài khoản:
 * điền brief + dán link Google Drive tài liệu → đơn vào "Chờ nhận", khách được chuyển sang trang theo dõi.
 */
class OrderFormController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $studio = $this->findStudio($slug);

        // Chủ studio bấm "Xem thử" ở Cài đặt: xem trang bằng mẫu khác mà chưa cần lưu. Khách không dùng được.
        $preview = (string) $request->query('xem-thu');
        $previewing = $preview !== '' && array_key_exists($preview, config('net.page_themes'))
            && $request->user()?->id === $studio->owner_id;

        return response()->view('public.order-form', [
            'studio' => $studio,
            'theme' => $previewing ? $preview : $studio->pageTheme(),
            'previewing' => $previewing,
            'services' => $studio->services()->where('is_public', true)->orderBy('sort_order')->orderBy('id')->get(),
            'works' => $studio->portfolioItems()->get(),
            'consultSlots' => $studio->consultEnabled() ? app(\App\Services\ConsultSlots::class)->forStudio($studio) : null,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(Request $request, OrderService $orders, ClientDirectory $directory, string $slug): RedirectResponse
    {
        $studio = $this->findStudio($slug);
        abort_unless($studio->order_form_enabled, 404);

        if (! FormGuard::verify($request, 'order-'.$studio->id, $request->input('form_ts'), $request->input('form_sig'), $request->input('hp_key'))) {
            return back()->withInput()->withErrors(['title' => 'Phiên đặt đơn đã hết hạn. Vui lòng tải lại trang và gửi lại.']);
        }

        // Không bắt buộc SĐT — nhưng phải có ít nhất 1 trong 3: SĐT, email, Facebook.
        $directory->prepare($request);

        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('studio_id', $studio->id)->where('is_public', true)->whereNull('deleted_at')],
            'title' => 'required|string|max:160',
            'brief' => 'required|string|max:10000',
            'style' => 'nullable|string|max:500',
            'colors' => 'nullable|string|max:255',
            'specs' => 'nullable|string|max:500',
            'copy_text' => 'nullable|string|max:5000',
            'budget_note' => 'nullable|string|max:120',
            'client_due_on' => 'nullable|date|after_or_equal:today',
            'links' => 'nullable|array|max:5',
            'links.*' => 'nullable|url:http,https|max:2000',
            'name' => 'required|string|max:100',
            'agree' => $studio->policy ? 'accepted' : 'nullable',
        ] + $directory->rules(), $directory->messages() + [
            'title.required' => 'Hãy cho biết bạn cần thiết kế gì.',
            'brief.required' => 'Hãy mô tả yêu cầu — càng rõ càng nhanh có phương án ưng ý.',
            'links.*.url' => 'Link không hợp lệ — dán đầy đủ link chia sẻ, bắt đầu bằng https://',
            'client_due_on.after_or_equal' => 'Ngày mong muốn phải từ hôm nay trở đi.',
            'agree.accepted' => 'Hãy đọc và đồng ý với quy định làm việc trước khi gửi.',
        ]);

        // Mẫu brief của dịch vụ đã chọn: kiểm tra câu bắt buộc, chỉ nhận lựa chọn có thật.
        $service = isset($data['service_id']) ? $studio->services()->find($data['service_id']) : null;
        $brief = $service ? $service->collectAnswers((array) $request->input('answers.'.$service->id, [])) : ['answers' => [], 'errors' => []];
        if ($brief['errors']) {
            return back()->withInput()->withErrors($brief['errors']);
        }

        // Khách quen (trùng SĐT, email hoặc link Facebook) → gắn vào hồ sơ có sẵn và chỉ BỔ SUNG liên hệ còn trống.
        // Không ghi đè tên/liên hệ freelancer đã lưu: người lạ gõ trùng SĐT/email trên trang công khai không sửa được hồ sơ khách.
        $contacts = ['phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null, 'facebook' => $data['facebook'] ?? null];
        $match = $directory->match($studio, $contacts['phone'], $contacts['email'], $contacts['facebook']);
        $client = $match['client'] ?? $studio->clients()->make(['name' => $data['name']]);
        $directory->fillMissing($client, $contacts);
        $client->studio_id = $studio->id;
        $client->save();

        $links = collect($data['links'] ?? [])->filter()->map(fn ($url) => ['url' => $url])->values()->all();

        $order = $orders->create($studio, $client, $data, 'form', $service, $links, 'new');
        if ($brief['answers']) {
            $order->forceFill(['brief_answers' => $brief['answers']])->save();
        }

        // Ghi lại (chỉ freelancer thấy) khi khách xưng tên khác với tên đã lưu — để biết đơn được ghép vào ai và vì sao.
        if ($match && mb_strtolower(trim($data['name'])) !== mb_strtolower(trim((string) $client->name))) {
            $orders->log($order, 'note', 'Người đặt ghi tên “'.$data['name'].'” — Nét nhận ra là khách “'.$client->name.'” qua '.ClientDirectory::matchLabel($match['by']).'.', 'system', false);
        }

        return redirect()->route('public.track', $order->tracking_token)->with('placed', true);
    }

    /**
     * Ảnh xem trước khi chia sẻ link (og:image). Tạo 1 lần rồi lưu lại; link có ?v=mã nội dung nên cho phép
     * trình duyệt / Facebook / Zalo giữ lâu. Máy chủ không vẽ được thì dùng ảnh mặc định của Nét.
     */
    public function shareImage(ShareImage $images, string $slug): BinaryFileResponse|RedirectResponse
    {
        $studio = $this->findStudio($slug);
        $path = $images->path($studio);

        if (! $path) {
            return redirect()->to(asset('og-image.png'));
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function findStudio(string $slug): Studio
    {
        $studio = Studio::query()->where('slug', $slug)->first();
        abort_unless($studio && $studio->is_active, 404);

        return $studio;
    }
}
