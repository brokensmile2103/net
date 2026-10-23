<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\ProposalImage;
use App\Models\Round;
use App\Models\Service;
use App\Models\Studio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mọi thay đổi quan trọng của 1 đơn đi qua đây để: (1) tiền luôn khớp (total/paid là cache),
 * (2) mỗi bước đều ghi dòng thời gian — khách thấy phần visible trên trang theo dõi.
 */
class OrderService
{
    public function __construct(private ImageService $images) {}

    // ======================================================================
    // Tạo đơn
    // ======================================================================

    /**
     * @param  array  $data  title, brief, style, colors, specs, copy_text, budget_note, due_on, client_due_on, priority,
     *                       revision_rounds, deposit_percent, internal_note
     * @param  list<array{url: string, label?: ?string}>  $links  link tài liệu tham khảo (Drive...)
     */
    public function create(Studio $studio, Client $client, array $data, string $source = 'manual', ?Service $service = null, array $links = [], string $status = 'new'): Order
    {
        abort_unless((int) $client->studio_id === (int) $studio->id, 404);

        return DB::transaction(function () use ($studio, $client, $data, $source, $service, $links, $status) {
            $locked = Studio::query()->whereKey($studio->id)->lockForUpdate()->firstOrFail();
            $locked->order_seq++;
            $locked->save();
            $studio->order_seq = $locked->order_seq;

            $order = new Order([
                'client_id' => $client->id,
                'service_id' => $service?->id,
                'title' => $data['title'],
                'priority' => $data['priority'] ?? 'normal',
                'brief' => $data['brief'],
                'style' => $data['style'] ?? null,
                'colors' => $data['colors'] ?? null,
                'specs' => $data['specs'] ?? null,
                'copy_text' => $data['copy_text'] ?? null,
                'budget_note' => $data['budget_note'] ?? null,
                'due_on' => $data['due_on'] ?? null,
                'client_due_on' => $data['client_due_on'] ?? null,
                'revision_rounds' => $data['revision_rounds'] ?? $service?->effectiveRevisionRounds($studio) ?? $studio->default_revision_rounds,
                'deposit_percent' => $data['deposit_percent'] ?? $service?->effectiveDepositPercent($studio) ?? $studio->default_deposit_percent,
                'lock_delivery' => $data['lock_delivery'] ?? $studio->lock_delivery_until_paid,
                'internal_note' => $data['internal_note'] ?? null,
            ]);
            $order->studio_id = $studio->id;
            $order->code = 'N'.str_pad((string) $locked->order_seq, 4, '0', STR_PAD_LEFT);
            $order->status = array_key_exists($status, config('net.statuses')) && $status !== 'cancelled' ? $status : 'new';
            $order->source = $source === 'form' ? 'form' : 'manual';
            $order->tracking_token = Str::random(40);
            if ($order->status === 'in_progress') {
                $order->started_at = now();
            }
            $order->save();

            // Giá đã chốt với khách (nhập tay hoặc AI đọc từ đoạn chat) được ưu tiên hơn giá niêm yết của dịch vụ.
            $agreed = isset($data['price']) && is_numeric($data['price']) && (float) $data['price'] > 0 ? round((float) $data['price'], 2) : null;
            if ($agreed !== null || ($service && $service->price !== null)) {
                $order->charges()->create(['kind' => 'base', 'label' => $service?->name ?? config('net.charge_kinds.base'), 'amount' => $agreed ?? $service->price, 'sort_order' => 0]);
                $order->syncTotals();
            }

            foreach (array_slice($links, 0, 10) as $link) {
                if (filled($link['url'] ?? null)) {
                    $order->links()->create(['kind' => 'reference', 'label' => $link['label'] ?? null, 'url' => $link['url'], 'from_client' => $source === 'form']);
                }
            }

            $source === 'form'
                ? $this->log($order, 'created', 'Khách gửi yêu cầu qua trang đặt đơn.', 'client')
                : $this->log($order, 'created', 'Đơn được tạo.');

            return $order;
        });
    }

    // ======================================================================
    // Báo giá & giai đoạn
    // ======================================================================

    /**
     * Thay toàn bộ các dòng báo giá. Không cho tổng mới nhỏ hơn số tiền đã thu.
     *
     * @param  list<array{kind: string, label: string, amount: float|int|string}>  $rows
     */
    public function setCharges(Order $order, array $rows): Order
    {
        return DB::transaction(function () use ($order, $rows) {
            $order->charges()->delete();

            foreach (array_values($rows) as $i => $row) {
                $amount = round(abs((float) ($row['amount'] ?? 0)), 2);
                if (blank($row['label'] ?? null) && $amount <= 0) {
                    continue;
                }
                $kind = array_key_exists($row['kind'] ?? '', config('net.charge_kinds')) ? $row['kind'] : 'extra';
                $order->charges()->create([
                    'kind' => $kind,
                    'label' => trim((string) ($row['label'] ?? '')) ?: config('net.charge_kinds.'.$kind),
                    'amount' => $amount,
                    'sort_order' => $i,
                ]);
            }

            $order->syncTotals();

            if ((float) $order->total_amount < (float) $order->paid_amount - 0.001) {
                throw ValidationException::withMessages(['charges' => 'Tổng báo giá ('.money($order->total_amount).'đ) không được nhỏ hơn số tiền đã thu ('.money($order->paid_amount).'đ).']);
            }

            // Báo giá đã gửi mà sửa lại → khách cần đồng ý lại.
            if ($order->quote_accepted_at && $order->wasChanged('total_amount')) {
                $order->quote_accepted_at = null;
                $order->save();
            }

            $this->log($order, 'quote', 'Cập nhật báo giá: '.money($order->total_amount).'đ.', 'designer', false);

            return $order;
        });
    }

    public function sendQuote(Order $order): Order
    {
        if ((float) $order->total_amount <= 0) {
            throw ValidationException::withMessages(['charges' => 'Hãy nhập báo giá trước khi gửi cho khách.']);
        }

        $order->quote_sent_at = now();
        if (in_array($order->status, ['new', 'quoted'], true)) {
            $order->status = 'quoted';
        }
        $order->save();

        $msg = 'Đã gửi báo giá: '.money($order->total_amount).'đ';
        if ($order->depositAmount() > 0) {
            $msg .= ', đặt cọc '.$order->deposit_percent.'% ('.money($order->depositAmount()).'đ)';
        }
        $this->log($order, 'quote', $msg.'.');

        return $order;
    }

    /** Khách bấm "Đồng ý báo giá" trên trang theo dõi. */
    public function acceptQuote(Order $order): Order
    {
        if ($order->isCancelled() || $order->quote_accepted_at || ! $order->quote_sent_at || (float) $order->total_amount <= 0) {
            throw ValidationException::withMessages(['quote' => 'Báo giá này không cần xác nhận nữa.']);
        }

        $order->quote_accepted_at = now();
        $order->save();
        $this->log($order, 'quote_accepted', 'Khách đồng ý báo giá '.money($order->total_amount).'đ.', 'client');

        return $order;
    }

    /** Đổi giai đoạn (kéo thả trên Bảng việc hoặc chọn ở trang đơn). Freelancer tự do chuyển, hệ thống ghi mốc thời gian. */
    public function changeStatus(Order $order, string $status, ?string $reason = null): Order
    {
        abort_unless(array_key_exists($status, config('net.statuses')), 422);

        if ($status === $order->status) {
            return $order;
        }

        if ($status === 'cancelled') {
            return $this->cancel($order, $reason);
        }

        if ($status === 'delivered' && $order->sourceLinksQuery()->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'Hãy thêm link file bàn giao (Google Drive) trước khi chuyển sang "Đã bàn giao".']);
        }

        $order->status = $status;
        $order->cancelled_at = null;
        $order->cancel_reason = null;
        if ($status === 'in_progress' && ! $order->started_at) {
            $order->started_at = now();
        }
        if ($status === 'delivered') {
            $order->delivered_at = now();
        } elseif ($order->delivered_at && $status !== 'delivered') {
            $order->delivered_at = null;
        }
        $order->save();

        $this->log($order, $status === 'delivered' ? 'delivered' : 'status', $status === 'delivered'
            ? 'Đã bàn giao file.'
            : 'Chuyển sang: '.config('net.statuses.'.$status.'.client_label').'.');

        return $order;
    }

    public function cancel(Order $order, ?string $reason): Order
    {
        $order->status = 'cancelled';
        $order->cancelled_at = now();
        $order->cancel_reason = $reason ? Str::limit(trim($reason), 250, '') : null;
        $order->save();

        $this->log($order, 'cancelled', 'Đơn đã huỷ'.($order->cancel_reason ? ': '.$order->cancel_reason : '').'.');

        return $order;
    }

    // ======================================================================
    // Vòng & phương án
    // ======================================================================

    /** Vòng nháp đang soạn (tạo mới nếu chưa có). Mỗi đơn chỉ có tối đa 1 vòng nháp. */
    public function draftRound(Order $order): Round
    {
        return DB::transaction(function () use ($order) {
            Order::query()->whereKey($order->id)->lockForUpdate()->first();

            $draft = $order->rounds()->where('status', 'draft')->first();
            if ($draft) {
                return $draft;
            }

            if ($order->rounds()->where('status', 'sent')->exists()) {
                throw ValidationException::withMessages(['round' => 'Vòng trước đang chờ khách phản hồi. Bạn vẫn có thể thêm phương án vào vòng đó.']);
            }

            return $order->rounds()->create(['number' => (int) $order->rounds()->max('number') + 1, 'status' => 'draft']);
        });
    }

    /**
     * Thêm 1 phương án (ảnh xem trước + link Drive) vào vòng. Vòng đã gửi vẫn thêm được (khách thấy ngay).
     *
     * @param  list<UploadedFile>  $files
     */
    public function addProposal(Round $round, array $data, array $files = []): Proposal
    {
        $order = $round->order;
        abort_if(in_array($round->status, ['approved', 'changes'], true), 422, 'Vòng này đã có phản hồi của khách — hãy tạo vòng mới.');

        if (count($files) === 0 && blank($data['drive_url'] ?? null)) {
            throw ValidationException::withMessages(['images' => 'Phương án cần ít nhất 1 ảnh xem trước hoặc 1 link file.']);
        }

        $count = $round->proposals()->count();
        $proposal = new Proposal([
            'label' => filled($data['label'] ?? null) ? $data['label'] : 'Phương án '.chr(65 + min($count, 25)),
            'note' => $data['note'] ?? null,
            'drive_url' => $data['drive_url'] ?? null,
            'sort_order' => $count,
        ]);
        $proposal->order_id = $order->id;
        $round->proposals()->save($proposal);

        $this->addImages($proposal, $files);

        if ($round->awaitsClient()) {
            $this->log($order, 'round_sent', 'Bổ sung '.$proposal->label.' vào vòng '.$round->label().'.');
        }

        return $proposal;
    }

    /** @param list<UploadedFile> $files */
    public function addImages(Proposal $proposal, array $files): void
    {
        $studio = $proposal->order->studio;
        $max = (int) config('net.images.per_proposal', 8);
        $existing = $proposal->images()->count();

        if ($existing + count($files) > $max) {
            throw ValidationException::withMessages(['images' => 'Mỗi phương án tối đa '.$max.' ảnh xem trước.']);
        }

        foreach (array_values($files) as $i => $file) {
            $stored = $this->images->store($file, $studio, 'proposals', (int) config('net.images.preview_max_px', 1800));
            $image = new ProposalImage($stored + ['sort_order' => $existing + $i]);
            $image->studio_id = $studio->id;
            $proposal->images()->save($image);
        }
    }

    public function deleteImage(ProposalImage $image): void
    {
        $studio = $image->proposal->order->studio;
        $this->images->delete($studio, $image->path, $image->bytes);
        $image->delete();
    }

    public function deleteProposal(Proposal $proposal): void
    {
        abort_if(in_array($proposal->round->status, ['approved', 'changes'], true), 422, 'Không xoá được phương án của vòng khách đã phản hồi.');

        DB::transaction(function () use ($proposal) {
            foreach ($proposal->images as $image) {
                $this->deleteImage($image);
            }
            $proposal->delete();
        });
    }

    /** Gửi vòng nháp cho khách → đơn sang "Chờ khách xem". */
    public function sendRound(Round $round, ?string $note = null): Round
    {
        abort_unless($round->isDraft(), 422, 'Vòng này đã gửi rồi.');

        $count = $round->proposals()->count();
        if ($count === 0) {
            throw ValidationException::withMessages(['round' => 'Thêm ít nhất 1 phương án trước khi gửi cho khách.']);
        }

        return DB::transaction(function () use ($round, $note, $count) {
            $round->update(['status' => 'sent', 'sent_at' => now(), 'note' => $note ?: $round->note]);

            $order = $round->order;
            $order->status = 'review';
            if (! $order->started_at) {
                $order->started_at = now();
            }
            $order->save();

            $this->log($order, 'round_sent', 'Đã gửi '.$count.' phương án — vòng '.$round->label().'.');

            return $round;
        });
    }

    // ======================================================================
    // Hành động của khách (trang theo dõi)
    // ======================================================================

    /** Vòng đang chờ khách phản hồi, hoặc lỗi nếu không có. */
    public function awaitingRound(Order $order): Round
    {
        $round = $order->rounds()->where('status', 'sent')->reorder('number', 'desc')->first();

        if (! $round || $order->isCancelled()) {
            throw ValidationException::withMessages(['round' => 'Hiện chưa có phương án nào chờ bạn phản hồi.']);
        }

        return $round;
    }

    public function clientChoose(Order $order, int $proposalId): Proposal
    {
        $round = $this->awaitingRound($order);
        $proposal = $round->proposals()->whereKey($proposalId)->firstOrFail();

        DB::transaction(function () use ($round, $proposal, $order) {
            $round->proposals()->update(['is_chosen' => false]);
            $proposal->forceFill(['is_chosen' => true])->save();
            $this->log($order, 'chosen', 'Khách chọn '.$proposal->label.' (vòng '.$round->label().').', 'client');
        });

        return $proposal;
    }

    /**
     * Khách duyệt hoặc yêu cầu sửa vòng đang chờ. Góp ý chung + các ghim trên ảnh được lưu kèm.
     *
     * @param  list<array{image_id: int|string, x: float|string, y: float|string, body: string}>  $pins
     */
    public function clientRespond(Order $order, string $decision, ?string $note, array $pins = []): Round
    {
        abort_unless(in_array($decision, ['approve', 'changes'], true), 422);
        $round = $this->awaitingRound($order);
        $proposals = $round->proposals()->with('images')->get();

        if ($decision === 'approve' && $proposals->count() > 1 && ! $proposals->contains('is_chosen', true)) {
            throw ValidationException::withMessages(['decision' => 'Hãy chọn 1 phương án trước khi duyệt.']);
        }

        if ($decision === 'changes' && blank($note) && count($pins) === 0) {
            throw ValidationException::withMessages(['note' => 'Hãy ghi góp ý (hoặc ghim lên ảnh) để mình biết cần sửa gì nhé.']);
        }

        // Chỉ nhận ghim nằm trên ảnh của CHÍNH vòng này.
        // (Không dùng flatMap: collapse đánh lại khoá số, làm sai map image_id → proposal_id.)
        $imageOwners = [];
        foreach ($proposals as $p) {
            foreach ($p->images as $img) {
                $imageOwners[$img->id] = $p->id;
            }
        }

        return DB::transaction(function () use ($order, $round, $decision, $note, $pins, $imageOwners, $proposals) {
            $round->update(['status' => $decision === 'approve' ? 'approved' : 'changes', 'responded_at' => now(), 'response_note' => $note ?: null]);

            if ($decision === 'approve' && $proposals->count() === 1) {
                $proposals->first()->forceFill(['is_chosen' => true])->save();
            }

            if (filled($note)) {
                $order->feedback()->create(['round_id' => $round->id, 'author' => 'client', 'body' => $note]);
            }

            foreach (array_slice($pins, 0, 30) as $pin) {
                $imageId = (int) ($pin['image_id'] ?? 0);
                $body = trim((string) ($pin['body'] ?? ''));
                if (! isset($imageOwners[$imageId]) || $body === '') {
                    continue;
                }
                $order->feedback()->create([
                    'round_id' => $round->id,
                    'proposal_id' => $imageOwners[$imageId],
                    'image_id' => $imageId,
                    'author' => 'client',
                    'body' => Str::limit($body, 1000, ''),
                    'pin_x' => max(0, min(100, round((float) ($pin['x'] ?? 0), 2))),
                    'pin_y' => max(0, min(100, round((float) ($pin['y'] ?? 0), 2))),
                ]);
            }

            if ($decision === 'approve') {
                $chosen = $round->proposals()->where('is_chosen', true)->first();
                $order->status = 'approved';
                $order->save();
                $this->log($order, 'approved', 'Khách duyệt '.($chosen?->label ?? 'phương án').' — vòng '.$round->label().'.', 'client');
            } else {
                $order->status = 'revising';
                $order->save();
                $used = $order->revisionsUsed();
                $over = $used > $order->revision_rounds;
                $this->log($order, 'changes', 'Khách yêu cầu sửa vòng '.$round->label().' (lần sửa '.$used.'/'.$order->revision_rounds.').', 'client');
                if ($over) {
                    $this->log($order, 'note', 'Vượt số lần sửa đã thoả thuận — cân nhắc thêm "Phí sửa thêm" vào báo giá.', 'system', false);
                }
            }

            return $round;
        });
    }

    /** Trao đổi trong đơn (không gắn vòng nào) — khách hoặc freelancer. */
    public function comment(Order $order, string $author, string $body): Feedback
    {
        $fb = $order->feedback()->create(['author' => $author === 'client' ? 'client' : 'designer', 'body' => Str::limit(trim($body), 2000, '')]);
        $this->log($order, 'feedback', $author === 'client' ? 'Khách gửi lời nhắn.' : 'Bạn trả lời khách.', $author === 'client' ? 'client' : 'designer', $author === 'client');

        return $fb;
    }

    // ======================================================================
    // Thanh toán
    // ======================================================================

    public function addPayment(Order $order, float $amount, Carbon $paidOn, string $method, ?string $note): Payment
    {
        return DB::transaction(function () use ($order, $amount, $paidOn, $method, $note) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Số tiền phải lớn hơn 0.']);
            }
            if ((float) $locked->total_amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Hãy nhập báo giá trước khi ghi nhận thu tiền.']);
            }
            if ($amount > $locked->balance() + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Số tiền ('.money($amount).'đ) lớn hơn số còn phải thu ('.money($locked->balance()).'đ).']);
            }

            $isDeposit = $locked->depositAmount() > 0 && (float) $locked->paid_amount <= 0.001 && $amount < (float) $locked->total_amount - 0.001;

            $payment = new Payment(['amount' => round($amount, 2), 'paid_on' => $paidOn->toDateString(), 'method' => $method, 'note' => $note, 'is_deposit' => $isDeposit]);
            $payment->studio_id = $locked->studio_id;
            $locked->payments()->save($payment);
            $locked->syncPaid();
            $order->setRawAttributes($locked->getAttributes(), true);

            $this->log($order, 'payment', ($isDeposit ? 'Đã nhận cọc ' : 'Đã nhận ').money($amount).'đ'.($order->balance() <= 0 ? ' — đã thanh toán đủ.' : '.'));

            return $payment;
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment->delete();
            $order->syncPaid();
            $this->log($order, 'payment', 'Xoá khoản thu '.money($payment->amount).'đ.', 'designer', false);
        });
    }

    // ======================================================================
    // Bàn giao, khác
    // ======================================================================

    /** @param list<array{url: string, label?: ?string}> $links */
    public function deliver(Order $order, array $links, ?string $note = null): Order
    {
        foreach ($links as $link) {
            if (filled($link['url'] ?? null)) {
                $order->links()->create(['kind' => 'source', 'label' => $link['label'] ?? null, 'url' => $link['url']]);
            }
        }

        if ($order->sourceLinksQuery()->doesntExist()) {
            throw ValidationException::withMessages(['links' => 'Hãy dán ít nhất 1 link file bàn giao.']);
        }

        if ($note) {
            $this->comment($order, 'designer', $note);
        }

        return $this->changeStatus($order, 'delivered');
    }

    public function regenerateToken(Order $order): Order
    {
        $order->tracking_token = Str::random(40);
        $order->save();
        $this->log($order, 'note', 'Đổi link theo dõi — link cũ không còn dùng được.', 'designer', false);

        return $order;
    }

    /** Xoá hẳn 1 đơn — chỉ khi chưa có khoản thu nào (tránh mất sổ sách tiền). Dọn luôn ảnh trên đĩa. */
    public function delete(Order $order): void
    {
        if ($order->payments()->exists()) {
            throw ValidationException::withMessages(['order' => 'Đơn đã có khoản thu — hãy huỷ đơn thay vì xoá.']);
        }

        DB::transaction(function () use ($order) {
            $studio = $order->studio;
            foreach (ProposalImage::query()->whereIn('proposal_id', $order->proposals()->select('id'))->get() as $img) {
                $this->images->delete($studio, $img->path, $img->bytes);
            }
            $this->images->delete($studio, $order->cover_path);
            $order->delete();
        });
    }

    public function log(Order $order, string $type, string $message, string $actor = 'designer', bool $visible = true): OrderEvent
    {
        return $order->events()->create([
            'type' => $type,
            'actor' => $actor,
            'visible' => $visible,
            'message' => Str::limit($message, 495, '…'),
        ]);
    }
}
