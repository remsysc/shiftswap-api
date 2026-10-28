<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreOwnershipTransferRequest extends FormRequest
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
            'successor_email' => ['required', 'email'],
        ];
    }

    /**
     * Validate successor eligibility.
     */
    protected function passedValidation(): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $currentUser = $this->user();
        $email = (string) $this->input('successor_email');

        /** @var User|null $successor */
        $successor = User::where('email', $email)->first();

        if ($successor === null || ! $successor->belongsToBusiness($business)) {
            throw ValidationException::withMessages([
                'successor_email' => ['Successor must be an active member of this business.'],
            ]);
        }

        if ($currentUser !== null && $successor->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'successor_email' => ['Cannot transfer ownership to yourself.'],
            ]);
        }

        $this->offsetSet('successor_id', $successor->id);
    }
}
