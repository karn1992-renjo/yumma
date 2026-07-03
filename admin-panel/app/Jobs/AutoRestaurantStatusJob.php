<?php

namespace App\Jobs;

use App\Models\Restaurant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoRestaurantStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;
    
    public function handle()
    {
        $restaurants = Restaurant::whereNotNull('open_time')
            ->whereNotNull('close_time')
            ->get();
            
        foreach ($restaurants as $restaurant) {
            $restaurant->autoCheckAndUpdateStatus();
        }
    }
}