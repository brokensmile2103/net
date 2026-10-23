<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 1 dòng trên báo giá. amount luôn dương; kind = discount thì được TRỪ khi cộng tổng. */
class OrderCharge extends Model
{
    protected $fillable = ['kind', 'label', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isDiscount(): bool
    {
        return $this->kind === 'discount';
    }

    public function signedAmount(): float
    {
        return $this->isDiscount() ? -(float) $this->amount : (float) $this->amount;
    }
}
