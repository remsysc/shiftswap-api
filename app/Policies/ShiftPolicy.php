<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    /**
     * Determine whether the user can view any shifts.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    /**
     * Determine whether the user can view the shift.
     */
    public function view(User $user, Shift $shift): bool
    {
        return $user->belongsToBusiness($shift->business);
    }

    /**
     * Determine whether the user can create shifts.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->isManagerOrOwner($business);
    }

    /**
     * Determine whether the user can update the shift.
     */
    public function update(User $user, Shift $shift): bool
    {
        return $user->isManagerOrOwner($shift->business);
    }

    /**
     * Determine whether the user can delete the shift.
     */
    public function delete(User $user, Shift $shift): bool
    {
        return $user->isManagerOrOwner($shift->business);
    }

    /**
     * Determine whether the user can assign staff to the shift.
     */
    public function assignStaff(User $user, Shift $shift): bool
    {
        return $user->isManagerOrOwner($shift->business);
    }
}
