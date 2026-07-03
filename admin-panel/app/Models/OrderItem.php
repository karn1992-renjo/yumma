<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'menu_item_id', 'quantity', 'unit_price', 'total_price',
        'selected_variant', 'selected_add_ons', 'special_instructions'
    ];

    protected $casts = [
        'selected_variant' => 'array',
        'selected_add_ons' => 'array',
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
