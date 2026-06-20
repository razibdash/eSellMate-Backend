<?php

namespace App\Listeners;

use App\Events\OrderStatusUpdated;
use App\Services\SmsService;

class SendOrderStatusSms
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    public function handle(OrderStatusUpdated $event): void
    {
        $this->sms->sendDeliveryUpdate($event->order, $event->status);
    }
}
