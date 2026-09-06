<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = ['fy', 'period', 'status', 'closed_at', 'closed_by'];

    protected $casts = ['closed_at' => 'datetime'];

    public static function isClosed(string $period): bool
    {
        return static::query()->where('period', $period)->where('status', 'closed')->exists();
    }
}
