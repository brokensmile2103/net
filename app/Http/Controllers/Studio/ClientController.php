<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Services\ClientDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    use ResolvesStudio;

    public function index(Request $request): View
    {
        $studio = $this->studio($request);
        $q = trim((string) $request->query('q'));

        $clients = $studio->clients()
            ->withCount('orders')
            ->withSum(['orders as revenue' => fn ($o) => $o->where('status', '!=', 'cancelled')], 'paid_amount')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%')->orWhere('facebook', 'like', '%'.$q.'%')->orWhere('company', 'like', '%'.$q.'%')))
            ->orderByDesc('last_order_at')->orderBy('name')
            ->paginate(40)->withQueryString();

        return view('clients.index', compact('studio', 'clients', 'q'));
    }

    public function store(Request $request, ClientDirectory $directory): RedirectResponse
    {
        $studio = $this->studio($request);
        $data = $this->validated($request, $directory);

        // Đã có khách trùng SĐT / email / link Facebook → mở hồ sơ có sẵn thay vì tạo trùng.
        if ($match = $directory->match($studio, $data['phone'] ?? null, $data['email'] ?? null, $data['facebook'] ?? null)) {
            return redirect()->route('clients.show', $match['client']->id)
                ->with('warning', 'Khách này đã có trong danh sách (trùng '.ClientDirectory::matchLabel($match['by']).') — đây là hồ sơ có sẵn, bấm “Sửa” nếu cần cập nhật.');
        }

        $client = $studio->clients()->create($data);

        return redirect()->route('clients.show', $client->id)->with('status', 'Đã thêm khách '.$client->name.'.');
    }

    public function show(Request $request, int $client): View
    {
        $studio = $this->studio($request);
        $client = $studio->clients()->findOrFail($client);
        $orders = $client->orders()->with('service')->latest('id')->get();

        return view('clients.show', [
            'studio' => $studio,
            'client' => $client,
            'orders' => $orders,
            'stats' => [
                'count' => $orders->where('status', '!=', 'cancelled')->count(),
                'paid' => (float) $orders->where('status', '!=', 'cancelled')->sum('paid_amount'),
                'due' => (float) $orders->where('status', '!=', 'cancelled')->sum(fn ($o) => max(0, $o->balance())),
            ],
        ]);
    }

    public function update(Request $request, ClientDirectory $directory, int $client): RedirectResponse
    {
        $studio = $this->studio($request);
        $client = $studio->clients()->findOrFail($client);
        $client->update($data = $this->validated($request, $directory));

        $back = back()->with('status', 'Đã lưu thông tin khách.');

        // Không chặn (có thể là người nhà dùng chung SĐT…), chỉ nhắc để freelancer biết có hồ sơ trùng.
        if ($match = $directory->match($studio, $data['phone'] ?? null, $data['email'] ?? null, $data['facebook'] ?? null, $client->id)) {
            $back->with('warning', 'Lưu ý: khách “'.$match['client']->name.'” cũng có '.ClientDirectory::matchLabel($match['by']).' này — đơn mới từ trang đặt đơn sẽ được ghép vào 1 trong 2 hồ sơ.');
        }

        return $back;
    }

    public function destroy(Request $request, int $client): RedirectResponse
    {
        $studio = $this->studio($request);
        $client = $studio->clients()->findOrFail($client);

        if ($client->orders()->exists()) {
            return back()->with('warning', 'Khách đã có đơn — không xoá được để giữ lịch sử đơn hàng.');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Đã xoá khách.');
    }

    private function validated(Request $request, ClientDirectory $directory): array
    {
        $directory->prepare($request);

        return $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:120',
            'contact_url' => 'nullable|url:http,https|max:255',
            'note' => 'nullable|string|max:2000',
        ] + $directory->rules(), $directory->messages() + ['contact_url.url' => 'Link không hợp lệ — dán đầy đủ, bắt đầu bằng https://']);
    }
}
