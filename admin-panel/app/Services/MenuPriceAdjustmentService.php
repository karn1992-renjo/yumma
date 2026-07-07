<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MenuPriceAdjustmentService
{
    public function adjust(Restaurant $restaurant, string $direction, string $type, float $value): int
    {
        if (!in_array($direction, ['increase', 'decrease'], true)
            || !in_array($type, ['percentage', 'fixed'], true)
            || $value <= 0
            || ($direction === 'decrease' && $type === 'percentage' && $value > 100)) {
            throw ValidationException::withMessages(['value' => 'Enter a valid price adjustment.']);
        }

        $count = 0;
        DB::transaction(function () use ($restaurant, $direction, $type, $value, &$count) {
            $restaurant->menuItems()->select(['id', 'price', 'discounted_price'])->chunkById(200, function ($items) use ($direction, $type, $value, &$count) {
                foreach ($items as $item) {
                    $item->price = $this->calculate((float) $item->price, $direction, $type, $value);
                    if ($item->discounted_price !== null) {
                        $item->discounted_price = min(
                            $item->price,
                            $this->calculate((float) $item->discounted_price, $direction, $type, $value)
                        );
                    }
                    $item->save();
                    $count++;
                }
            });
        });

        return $count;
    }

    private function calculate(float $price, string $direction, string $type, float $value): float
    {
        $change = $type === 'percentage' ? $price * ($value / 100) : $value;
        return round(max(0, $direction === 'increase' ? $price + $change : $price - $change), 2);
    }
}
