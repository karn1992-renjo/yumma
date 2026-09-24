<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * swaad-only: a screening on one of a theatre's screens.
 *
 * Scheduling data, never ticketing -- swaad does not sell tickets, so
 * there is no seat inventory here. A show answers three operational
 * questions and nothing else: when is the interval (so food can be timed
 * to arrive with the lights), when does ordering close (so nothing is
 * cooked for an auditorium that is emptying), and which film an order
 * belongs to.
 */
class TheatreShow extends Model
{
    /**
     * How long before the credits roll we stop accepting orders. Ordering
     * right up to the end guarantees food that arrives to an empty seat.
     */
    public const ORDER_CUTOFF_MINUTES = 10;

    /**
     * How long before the interval a held order is handed to the kitchen.
     *
     * One number rather than a per-item calculation on purpose: cinema
     * concession menus are popcorn, nachos and drinks, all of which are
     * assembled in minutes, and the real constraint is the rush -- every
     * interval order for a screen lands in the same few minutes. A fixed
     * lead keeps that surge predictable for the counter, which matters
     * more than shaving two minutes off any single order.
     */
    public const RELEASE_LEAD_MINUTES = 20;

    protected $fillable = [
        'theatre_id',
        'screen_id',
        'movie_title',
        'language',
        'starts_at',
        'interval_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'interval_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(TheatreScreen::class, 'screen_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'show_id');
    }

    /**
     * The screening a seat is sitting in right now.
     *
     * Deliberately starts a little BEFORE the advertised start time: the
     * trailers are the single best moment to order, and a customer who
     * scans while the ads are running should be offered that show's
     * interval, not told there is nothing playing.
     */
    public function scopeLive(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?: now();

        return $query->where('is_active', true)
            ->where('starts_at', '<=', $at->copy()->addMinutes(20))
            ->where('ends_at', '>=', $at);
    }

    public function isAcceptingOrders(?Carbon $at = null): bool
    {
        $at = $at ?: now();

        return $this->is_active && $at->lt($this->orderCutoffAt());
    }

    public function orderCutoffAt(): Carbon
    {
        return $this->ends_at->copy()->subMinutes(self::ORDER_CUTOFF_MINUTES);
    }

    /**
     * Whether "bring it at the interval" can still be offered: there has
     * to be an interval, and it has to be far enough away that the kitchen
     * can actually make the food before it.
     */
    public function canDeliverAtInterval(int $prepMinutes = 0, ?Carbon $at = null): bool
    {
        if ($this->interval_at === null) {
            return false;
        }

        $at = $at ?: now();

        return $this->interval_at->gt($at->copy()->addMinutes($prepMinutes));
    }
}
