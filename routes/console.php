<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('shopbot:about', function () {
    $this->info('ShopBot BD API');
});

Schedule::command('subscriptions:expire')->hourly();
