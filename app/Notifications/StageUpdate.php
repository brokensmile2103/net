<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Studio;
use App\Services\Mail\EmailContent;
use App\Services\Mail\StageContent;

/** Email báo tiến độ đơn cho khách theo giai đoạn (App\Services\Mail\StageMail). */
class StageUpdate extends StudioNotification
{
    public function __construct(public Order $order, public string $stage, public ?string $note = null, public bool $preview = false) {}

    public function studio(): Studio
    {
        return $this->order->studio;
    }

    public function subject(): string
    {
        return StageContent::build($this->order, $this->stage, $this->note, $this->preview)[0];
    }

    public function content(): EmailContent
    {
        return StageContent::build($this->order, $this->stage, $this->note, $this->preview)[1];
    }
}
