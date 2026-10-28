<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignShiftStaffRequest;
use App\Http\Resources\ShiftStaffResource;
use App\Models\Business;
use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\User;
use App\Notifications\SendShiftAssignedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShiftStaffController extends Controller
{
    /**
     * Assign a staff member to a shift.
     */
    public function store(AssignShiftStaffRequest $request, Business $business, Shift $shift): JsonResponse
    {
        abort_if($shift->business_id !== $business->id, 403);
        Gate::authorize('assignStaff', $shift);

        $userId = (int) $request->input('user_id');

        $isAlreadyAssigned = $shift->assignedStaff()
            ->where('user_id', $userId)
            ->exists();

        if ($isAlreadyAssigned) {
            throw ValidationException::withMessages([
                'user_id' => ['This staff member is already assigned to this shift.'],
            ]);
        }

        /** @var ShiftStaff $assignment */
        $assignment = $shift->shiftStaff()->create([
            'user_id' => $userId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $assignment->load('user');

        $assignment->user->notify(new SendShiftAssignedNotification($shift));

        return (new ShiftStaffResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove a staff member's assignment from a shift.
     */
    public function destroy(Business $business, Shift $shift, User $user): Response
    {
        abort_if($shift->business_id !== $business->id, 403);
        Gate::authorize('assignStaff', $shift);

        /** @var ShiftStaff|null $assignment */
        $assignment = $shift->assignedStaff()
            ->where('user_id', $user->id)
            ->first();

        if ($assignment !== null) {
            $assignment->update([
                'status' => 'removed',
                'removed_at' => now(),
                'removed_reason' => 'manager_removed',
            ]);
        }

        return response()->noContent();
    }
}
