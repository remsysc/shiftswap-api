<?php

namespace App\Http\Requests;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
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
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('business_id', $businessId),
            ],
            'role_label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_at.after' => 'End time must be after start time.',
        ];
    }
}
