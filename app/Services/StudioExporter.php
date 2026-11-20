<?php

namespace App\Services;

use App\Models\Studio;

/** Xuất toàn bộ dữ liệu của 1 studio ra JSON — "dữ liệu của bạn thuộc về bạn". Ảnh xuất dưới dạng đường link. */
class StudioExporter
{
    public function export(Studio $studio): array
    {
        $orders = $studio->orders()
            ->with(['charges', 'links', 'rounds.proposals.images', 'feedback', 'payments', 'events'])
            ->orderBy('id')->get()
            ->map(function ($o) {
                $row = $o->makeVisible('tracking_token')->toArray();
                $row['cover_url'] = $o->coverUrl();
                $row['tracking_url'] = $o->trackingUrl();

                return $row;
            });

        return [
            'app' => 'Nét',
            'exported_at' => now()->toIso8601String(),
            'studio' => $studio->only(['id', 'name', 'slug', 'tagline', 'bio', 'phone', 'email', 'contact_url', 'policy', 'default_deposit_percent', 'default_revision_rounds', 'page_theme', 'page_headline', 'ga_measurement_id', 'created_at'])
                + ['accent' => $studio->accentHex(), 'cover_url' => $studio->coverUrl(), 'email_settings' => array_diff_key($studio->emailPrefs(), array_flip(['limit', 'blocked']))],
            'portfolio' => $studio->portfolioItems()->get()->map(fn ($w) => ['url' => $w->url(), 'caption' => $w->caption])->all(),
            'services' => $studio->services()->withTrashed()->orderBy('id')->get()->toArray(),
            'clients' => $studio->clients()->orderBy('id')->get()->toArray(),
            'orders' => $orders->all(),
            'emails_sent' => $studio->emailLogs()->orderBy('id')->get(['order_id', 'kind', 'to_email', 'subject', 'status', 'sent_at', 'created_at'])->toArray(),
            'page_views_daily' => $studio->pageVisits()->selectRaw('day, source, device, count(*) as visitors, sum(views) as views')->groupBy('day', 'source', 'device')->orderBy('day')->get()->toArray(),
        ];
    }
}
