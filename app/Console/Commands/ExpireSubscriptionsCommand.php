<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark subscriptions as expired after their end date.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expirePastDueSubscriptions();
        $this->info("Expired subscriptions updated: {$count}");

        return self::SUCCESS;
    }
}
