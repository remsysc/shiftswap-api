<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\TimeOffRequest;
use App\Models\User;

class TimeOffRequestPolicy
{
    /**
     * Determine whether the user can view any time off requests.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    /**
     * Determine whether the user can view the specific time off request.
     */
    public function view(User $user, TimeOffRequest $request): bool
    {
        if ($request->user_id === $user->id) {
            return true;
        }

        return $user->isManagerOrOwner($request->business);
    }

    /**
     * Determine whether the user can create a time off request.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->belongsToBusiness($business);
    }

    /**
     * Determine whether the user can approve the time off request.
     */
    public function approve(User $user, TimeOffRequest $request): bool
    {
        return $user->isManagerOrOwner($request->business);
    }

    /**
     * Determine whether the user can reject the time off request.
     */
    public function reject(User $user, TimeOffRequest $request): bool
    {
        return $user->isManagerOrOwner($request->business);
    }
}
