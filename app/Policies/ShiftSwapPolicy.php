<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\ShiftSwap;
use App\Models\User;

class ShiftSwapPolicy
{
    /**
     * Determine whether the user can view any shift swaps.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    /**
     * Determine whether the user can create a shift swap offer.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    /**
     * Determine whether the user can request to take an offered swap.
     */
    public function request(User $user, ShiftSwap $swap): bool
    {
        return $user->belongsToBusiness($swap->business);
    }

    /**
     * Determine whether the user can cancel a swap offer.
     */
    public function cancel(User $user, ShiftSwap $swap): bool
    {
        return $swap->offered_by === $user->id;
    }

    /**
     * Determine whether the user can approve a swap request.
     */
    public function approve(User $user, ShiftSwap $swap): bool
    {
        return $user->isManagerOrOwner($swap->business);
    }

    /**
     * Determine whether the user can reject a swap request.
     */
    public function reject(User $user, ShiftSwap $swap): bool
    {
        return $user->isManagerOrOwner($swap->business);
    }
}
