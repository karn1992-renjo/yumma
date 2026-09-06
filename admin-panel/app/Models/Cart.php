<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mirror of a customer's in-progress cart for one restaurant, synced from
 * the customer app (App\Http\Controllers\Api\CartController) on every cart
 * mutation. One row per (customer_id, restaurant_id); an empty `items`
 * array means the customer cleared that restaurant's cart, and the row is
 * deleted rather than kept empty (see CartController::sync()). Used by
 * App\Services\CartRecoveryService to find genuinely abandoned carts.
 */
class Cart extends Model
{
    protected $fillable = [
        'customer_id',
        'restaurant_id',
        'items',
        'subtotal',
        'last_activity_at',
        'notified_at',
        'coupon_sent_at',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'last_activity_at' => 'datetime',
        'notified_at' => 'datetime',
        'coupon_sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
