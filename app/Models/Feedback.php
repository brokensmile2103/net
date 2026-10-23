<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Góp ý trên đơn — có thể ghim vào 1 điểm của ảnh phương án (pin_x, pin_y tính theo %). */
class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['round_id', 'proposal_id', 'image_id', 'author', 'body', 'pin_x', 'pin_y', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'pin_x' => 'float',
            'pin_y' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function image()
    {
        return $this->belongsTo(ProposalImage::class, 'image_id');
    }

    public function hasPin(): bool
    {
        return $this->image_id !== null && $this->pin_x !== null && $this->pin_y !== null;
    }

    public function fromClient(): bool
    {
        return $this->author === 'client';
    }
}
