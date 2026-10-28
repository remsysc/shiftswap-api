<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    /**
     * Determine whether the user can view any locations.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    /**
     * Determine whether the user can view the location.
     */
    public function view(User $user, Location $location): bool
    {
        return $user->belongsToBusiness($location->business);
    }

    /**
     * Determine whether the user can create locations.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->isManagerOrOwner($business);
    }

    /**
     * Determine whether the user can update the location.
     */
    public function update(User $user, Location $location): bool
    {
        return $user->isManagerOrOwner($location->business);
    }

    /**
     * Determine whether the user can delete the location.
     */
    public function delete(User $user, Location $location): bool
    {
        return $user->isManagerOrOwner($location->business);
    }
}
