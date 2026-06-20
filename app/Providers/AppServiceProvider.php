<?php

namespace App\Providers;

use App\Events\OrderPlaced;
use App\Events\OrderStatusUpdated;
use App\Listeners\SendOrderConfirmationSms;
use App\Listeners\SendOrderStatusSms;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Event::listen(OrderPlaced::class, SendOrderConfirmationSms::class);
        Event::listen(OrderStatusUpdated::class, SendOrderStatusSms::class);
    }
}
