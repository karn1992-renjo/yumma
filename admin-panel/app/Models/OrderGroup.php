<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parent record for a theatre checkout that spans multiple stalls: one
 * payment, fanned out into one real `orders` row per stall (each tagged
 * with this group's id via orders.order_group_id). See
 * movie-theatre-in-seat-ordering memory / plan for the full checkout design
 * -- the checkout engine itself lands in a later phase; this model exists
 * now so `orders.order_group_id` has somewhere to point.
 */
class OrderGroup extends Model
{
    protected $fillable = [
        'group_number',
        'user_id',
        'theatre_id',
        'screen_id',
        'show_id',
        'seat_label',
        'delivery_timing',
        'scheduled_for',
        'total_amount',
        'payment_status',
        'payment_reference',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'scheduled_for' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(TheatreScreen::class, 'screen_id');
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(TheatreShow::class, 'show_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'order_group_id');
    }
}
