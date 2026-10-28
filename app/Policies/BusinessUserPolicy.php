<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessUserPolicy
{
    /**
     * Determine whether the user can view any staff/business users.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $user->isManagerOrOwner($business);
    }

    /**
     * Determine whether the user can update a staff/business user's role.
     */
    public function update(User $user, Business $business): bool
    {
        return $user->isOwner($business);
    }

    /**
     * Determine whether the user can delete a staff/business user.
     */
    public function delete(User $user, Business $business): bool
    {
        return $user->isOwner($business);
    }
}
