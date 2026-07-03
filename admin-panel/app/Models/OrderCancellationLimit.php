<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCancellationLimit extends Model
{
    protected $fillable = [
        'type', 'warning_threshold', 'penalty_threshold',
        'penalty_amount', 'auto_disable', 'cancellation_window_minutes'
    ];
    
    protected $casts = [
        'auto_disable' => 'boolean',
        'cancellation_window_minutes' => 'integer',
    ];

    public static function windowMinutesFor(string $type, int $fallbackMinutes = 15): int
    {
        $minutes = (int) (self::where('type', $type)->value('cancellation_window_minutes') ?? $fallbackMinutes);

        return max(0, $minutes);
    }

    public static function isWithinWindow($order, string $type, int $fallbackMinutes = 15): bool
    {
        $minutes = self::windowMinutesFor($type, $fallbackMinutes);

        if ($minutes === 0) {
            return true;
        }

        return optional($order->created_at)->addMinutes($minutes)->greaterThanOrEqualTo(now());
    }
    
    public static function checkAndApplyPenalty($type, $cancellationRate)
    {
        $limit = self::where('type', $type)->first();
        
        if (!$limit) {
            return null;
        }
        
        if ($cancellationRate >= $limit->penalty_threshold) {
            return [
                'penalty_applied' => true,
                'penalty_amount' => $limit->penalty_amount,
                'auto_disabled' => $limit->auto_disable
            ];
        }
        
        if ($cancellationRate >= $limit->warning_threshold) {
            return [
                'warning' => true,
                'message' => "Your cancellation rate is {$cancellationRate}%. Please maintain below {$limit->warning_threshold}%"
            ];
        }
        
        return null;
    }
}
