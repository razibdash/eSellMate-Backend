<?php

namespace App\Policies;

use App\Models\FlashSale;
use App\Models\User;

class FlashSalePolicy extends BaseBusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FlashSale $flashSale): bool
    {
        return $flashSale->business_id === $this->currentBusinessId();
    }

    public function delete(User $user, FlashSale $flashSale): bool
    {
        return $flashSale->business_id === $this->currentBusinessId();
    }

    private function currentBusinessId(): ?int
    {
        return request()->attributes->get('current_business')?->id;
    }
}
