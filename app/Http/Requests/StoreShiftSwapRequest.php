<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreShiftSwapRequest extends FormRequest
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
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Validate ownership and future time constraints.
     */
    protected function passedValidation(): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $user = $this->user();

        /** @var Shift $shift */
        $shift = Shift::findOrFail($this->input('shift_id'));

        if ($shift->business_id !== $business->id) {
            abort(403, 'Shift does not belong to this business.');
        }

        // FR-25: Must be assigned to the shift
        $assignment = ShiftStaff::where('shift_id', $shift->id)
            ->where('user_id', $user?->id)
            ->where('status', 'assigned')
            ->first();

        if ($assignment === null) {
            abort(403, 'You are not assigned to this shift.');
        }

        // FR-26: Cannot offer past shift
        if ($shift->start_at->isPast()) {
            throw ValidationException::withMessages([
                'shift_id' => ['Cannot offer a past shift for swap.'],
            ]);
        }

        // Check if an active offer already exists for this assignment
        $hasActiveOffer = ShiftSwap::where('shift_staff_id', $assignment->id)
            ->whereIn('status', ['offered', 'pending_approval'])
            ->exists();

        if ($hasActiveOffer) {
            throw ValidationException::withMessages([
                'shift_id' => ['An active swap offer already exists for this assignment.'],
            ]);
        }

        $this->offsetSet('shift_staff_id', $assignment->id);
    }
}
