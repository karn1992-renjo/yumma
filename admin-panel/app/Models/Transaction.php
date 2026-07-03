<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'type',
        'status',
        'razorpay_id',
        'transaction_id',
        'payment_method',
    ];
    
    protected $casts = [
        'amount' => 'float',
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
