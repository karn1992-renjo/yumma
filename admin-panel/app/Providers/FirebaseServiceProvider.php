<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (! class_exists(Factory::class) || ! class_exists(Messaging::class)) {
            return;
        }

        $this->app->singleton(Messaging::class, function ($app) {
            if (! config('firebase.credentials')) {
                return null;
            }

            $factory = (new Factory)
                ->withServiceAccount(config('firebase.credentials'));
                
            return $factory->createMessaging();
        });
    }
}
