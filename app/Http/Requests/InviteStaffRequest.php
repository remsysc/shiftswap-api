<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InviteStaffRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'role' => ['nullable', 'string', Rule::in(['manager', 'staff'])],
        ];
    }

    /**
     * Validate additional business constraints.
     */
    protected function passedValidation(): void
    {
        $email = (string) $this->input('email');

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This user already belongs to a business.'],
            ]);
        }
    }
}
