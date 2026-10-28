<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\TimeOffRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreTimeOffRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'End date must be on or after start date.',
        ];
    }

    /**
     * Validate against overlapping pending or approved time-off requests.
     */
    protected function passedValidation(): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $user = $this->user();

        if ($user === null) {
            return;
        }

        $startDate = Carbon::parse((string) $this->input('start_date'))->toDateString();
        $endDate = Carbon::parse((string) $this->input('end_date'))->toDateString();

        $hasOverlap = TimeOffRequest::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_date' => ['You already have a time-off request for this period.'],
            ]);
        }
    }
}
