<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftConflictService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class AssignShiftStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Validate business membership and overlap conflicts.
     */
    protected function passedValidation(): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        /** @var Shift $shift */
        $shift = $this->route('shift');

        abort_if($shift->business_id !== $business->id, 403);

        /** @var User $targetUser */
        $targetUser = User::findOrFail($this->input('user_id'));

        if (! $targetUser->belongsToBusiness($business)) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user does not belong to this business.'],
            ]);
        }

        if ($shift->assignedStaff()->where('user_id', $targetUser->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['This staff member is already assigned to this shift.'],
            ]);
        }

        $conflictService = app(ShiftConflictService::class);

        if ($conflictService->hasConflict($targetUser, $shift->start_at, $shift->end_at, $shift->id, $business->timezone)) {
            throw ValidationException::withMessages([
                'user_id' => ['This staff member has a conflicting shift or approved time-off.'],
            ]);
        }
    }
}
