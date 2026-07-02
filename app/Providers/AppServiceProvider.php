<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatically log all outgoing emails for delivery tracking
        Event::listen(
            MessageSent::class,
            \App\Listeners\LogSentEmail::class,
        );
    }
}
