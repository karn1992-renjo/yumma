<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'restaurant_id',
        'order_id',
        'rating',
        'comment',
        'images',
        'is_verified',
        'status'
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array',
        'is_verified' => 'boolean',
        'status' => 'string'
    ];

    /**
     * Get the user who wrote the review
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the restaurant being reviewed
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the order associated with the review
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for approved reviews only
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')->where('is_verified', true);
    }

    /**
     * Scope for pending reviews
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}