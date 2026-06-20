<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\SmsService;

class SendOrderConfirmationSms
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        $this->sms->sendOrderConfirmation($event->order);
    }
}
