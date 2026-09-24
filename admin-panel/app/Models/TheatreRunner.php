<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persistent "this user works as a runner at this theatre" roster entry
 * -- separate from their per-shift DriverGig rows (a roster entry lets a
 * theatre owner manage who's ever allowed to be scheduled, independent of
 * any particular shift). Mirrors branch_drivers.
 */
class TheatreRunner extends Model
{
    protected $fillable = ['theatre_id', 'runner_id', 'approval_status'];

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'runner_id');
    }
}
