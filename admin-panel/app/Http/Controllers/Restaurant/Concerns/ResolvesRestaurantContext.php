<?php

namespace App\Http\Controllers\Restaurant\Concerns;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Auth;

trait ResolvesRestaurantContext
{
    protected function currentRestaurant(): ?Restaurant
    {
        return Auth::user()?->activeRestaurant();
    }
}
