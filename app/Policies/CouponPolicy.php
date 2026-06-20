<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Http\Request;

class CouponPolicy extends BaseBusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $coupon->business_id === $this->currentBusinessId();
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $coupon->business_id === $this->currentBusinessId();
    }

    private function currentBusinessId(): ?int
    {
        return request()->attributes->get('current_business')?->id;
    }
}
