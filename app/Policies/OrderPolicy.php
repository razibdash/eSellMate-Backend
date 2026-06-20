<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends BaseBusinessPolicy
{
    public function addPayment(User $user, Order $order): bool
    {
        return $order->business_id === request()->attributes->get('current_business')?->id;
    }
}
