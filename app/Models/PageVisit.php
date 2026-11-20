<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 1 người xem trang đặt đơn trong 1 ngày (xem App\Services\Analytics\PageAnalytics). */
class PageVisit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['day' => 'date', 'views' => 'integer'];
    }
}
