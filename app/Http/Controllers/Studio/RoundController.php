<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesStudio;
use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalImage;
use App\Models\Round;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Vòng gửi phương án (V1, V2...) và các phương án thiết kế trong mỗi vòng. */
class RoundController extends Controller
{
    use ResolvesStudio;

    public function store(Request $request, OrderService $service, int $order): RedirectResponse
    {
        $studio = $this->studio($request);
        $order = $studio->orders()->findOrFail($order);
        abort_if($order->isCancelled(), 422, 'Đơn đã huỷ.');

        $round = $service->draftRound($order);

        return redirect()->to(route('orders.show', $order->id).'#vong-'.$round->id);
    }

    public function update(Request $request, int $round): RedirectResponse
    {
        $round = $this->findRound($request, $round);
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $round->update(['note' => $data['note'] ?? null]);

        return back()->with('status', 'Đã lưu lời nhắn của vòng '.$round->label().'.');
    }

    public function send(Request $request, OrderService $service, int $round): RedirectResponse
    {
        $round = $this->findRound($request, $round);
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $service->sendRound($round, $data['note'] ?? null);

        return redirect()->to(route('orders.show', $round->order_id).'#vong-'.$round->id)
            ->with('status', 'Đã gửi vòng '.$round->label().' — gửi link theo dõi cho khách để xem nhé.');
    }

    /** Chỉ xoá được vòng nháp (khách chưa thấy). */
    public function destroy(Request $request, OrderService $service, int $round): RedirectResponse
    {
        $round = $this->findRound($request, $round);
        abort_unless($round->isDraft(), 422, 'Chỉ xoá được vòng nháp.');

        foreach ($round->proposals as $proposal) {
            $service->deleteProposal($proposal);
        }
        $orderId = $round->order_id;
        $round->delete();

        return redirect()->route('orders.show', $orderId)->with('status', 'Đã xoá vòng nháp.');
    }

    public function addProposal(Request $request, OrderService $service, int $round): RedirectResponse
    {
        $round = $this->findRound($request, $round);
        $data = $this->validateProposal($request, true);

        $proposal = $service->addProposal($round, $data, $request->file('images', []));

        return redirect()->to(route('orders.show', $round->order_id).'#vong-'.$round->id)->with('status', 'Đã thêm '.$proposal->label.'.');
    }

    public function updateProposal(Request $request, int $proposal): RedirectResponse
    {
        $proposal = $this->findProposal($request, $proposal);
        $data = $this->validateProposal($request, false);
        $proposal->update([
            'label' => $data['label'] ?: $proposal->label,
            'note' => $data['note'] ?? null,
            'drive_url' => $data['drive_url'] ?? null,
        ]);

        return back()->with('status', 'Đã lưu '.$proposal->label.'.');
    }

    public function addImages(Request $request, OrderService $service, int $proposal): RedirectResponse
    {
        $proposal = $this->findProposal($request, $proposal);
        abort_if(in_array($proposal->round->status, ['approved', 'changes'], true), 422, 'Vòng này khách đã phản hồi.');
        $request->validate([
            'images' => 'required|array|max:'.config('net.images.per_proposal'),
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb'),
        ], ['images.*.max' => 'Mỗi ảnh tối đa 15 MB.', 'images.*.image' => 'Chỉ nhận ảnh JPG, PNG, WebP.']);

        $service->addImages($proposal, $request->file('images'));

        return back()->with('status', 'Đã thêm ảnh vào '.$proposal->label.'.');
    }

    public function destroyProposal(Request $request, OrderService $service, int $proposal): RedirectResponse
    {
        $proposal = $this->findProposal($request, $proposal);
        $service->deleteProposal($proposal);

        return back()->with('status', 'Đã xoá phương án.');
    }

    public function destroyImage(Request $request, OrderService $service, int $image): RedirectResponse
    {
        $studio = $this->studio($request);
        $image = ProposalImage::query()->where('studio_id', $studio->id)->with('proposal.round')->findOrFail($image);
        abort_if(in_array($image->proposal->round->status, ['approved', 'changes'], true), 422, 'Không xoá được ảnh của vòng khách đã phản hồi.');
        $service->deleteImage($image);

        return back()->with('status', 'Đã xoá ảnh.');
    }

    // ---------------------------------------------------------------------

    private function findRound(Request $request, int $id): Round
    {
        $studio = $this->studio($request);

        return Round::query()->whereIn('order_id', $studio->orders()->select('id'))->with('order.studio')->findOrFail($id);
    }

    private function findProposal(Request $request, int $id): Proposal
    {
        $studio = $this->studio($request);

        return Proposal::query()->whereIn('order_id', $studio->orders()->select('id'))->with(['round', 'order.studio'])->findOrFail($id);
    }

    private function validateProposal(Request $request, bool $withImages): array
    {
        return $request->validate([
            'label' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:2000',
            'drive_url' => 'nullable|url:http,https|max:2000',
        ] + ($withImages ? [
            'images' => 'nullable|array|max:'.config('net.images.per_proposal'),
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:'.config('net.images.max_upload_kb'),
        ] : []), [
            'drive_url.url' => 'Link không hợp lệ — hãy dán đầy đủ, bắt đầu bằng https://',
            'images.*.max' => 'Mỗi ảnh tối đa 15 MB.',
            'images.*.image' => 'Chỉ nhận ảnh JPG, PNG, WebP.',
            'images.max' => 'Mỗi phương án tối đa '.config('net.images.per_proposal').' ảnh.',
        ]);
    }
}
