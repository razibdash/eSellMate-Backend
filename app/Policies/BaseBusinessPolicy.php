<?php

namespace App\Policies;

use App\Models\User;

class BaseBusinessPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }
}
