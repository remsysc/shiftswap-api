<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Determine whether the user can view invitations.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $user->isManagerOrOwner($business);
    }

    /**
     * Determine whether the user can create invitations.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->isManagerOrOwner($business);
    }
}
