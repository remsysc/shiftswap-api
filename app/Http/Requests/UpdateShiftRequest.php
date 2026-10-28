<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateShiftRequest extends FormRequest
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
        /** @var Business|null $business */
        $business = $this->route('business');
        $businessId = $business instanceof Business ? $business->id : $business;

        return [
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('business_id', $businessId),
            ],
            'role_label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Enforce chronological validity.
     */
    protected function passedValidation(): void
    {
        /** @var Shift $shift */
        $shift = $this->route('shift');

        $startAtInput = $this->input('start_at');
        $endAtInput = $this->input('end_at');

        $startAt = $startAtInput !== null ? Carbon::parse((string) $startAtInput) : $shift->start_at;
        $endAt = $endAtInput !== null ? Carbon::parse((string) $endAtInput) : $shift->end_at;

        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw ValidationException::withMessages([
                'end_at' => ['End time must be after start time.'],
            ]);
        }
    }
}
