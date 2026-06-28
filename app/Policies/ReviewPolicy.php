<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy extends BaseBusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function approve(User $user, Review $review): bool
    {
        return $review->business_id === $this->currentBusinessId();
    }

    private function currentBusinessId(): ?int
    {
        return request()->attributes->get('current_business')?->id;
    }
}
