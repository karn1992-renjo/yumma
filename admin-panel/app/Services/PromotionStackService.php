<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PromotionStackService
{
    public function __construct(private readonly PromotionStackingService $stacking)
    {
    }

    public function select(Collection $eligible): Collection
    {
        return $this->stacking->select($eligible);
    }
}
