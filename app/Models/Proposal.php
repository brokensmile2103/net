<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 1 phương án thiết kế trong 1 vòng: ảnh xem trước (đã nén) + link file trên Drive. */
class Proposal extends Model
{
    protected $fillable = ['label', 'note', 'drive_url', 'is_chosen', 'sort_order'];

    protected function casts(): array
    {
        return ['is_chosen' => 'boolean'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function images()
    {
        return $this->hasMany(ProposalImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }
}
