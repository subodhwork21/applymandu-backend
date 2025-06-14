<?php

namespace App\Providers;

use App\Events\EmailVerificationRequested;
use App\Listeners\SendEmailVerificationNotification;
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
        Event::listen(
            EmailVerificationRequested::class,
            SendEmailVerificationNotification::class
        );
    }
}
