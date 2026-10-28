<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSwap>
 */
class ShiftSwapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'shift_id' => Shift::factory(),
            'shift_staff_id' => ShiftStaff::factory(),
            'offered_by' => User::factory(),
            'requested_by' => null,
            'status' => 'offered',
            'cancellation_reason' => null,
            'notes' => fake()->optional()->sentence(),
            'manager_notes' => null,
        ];
    }
}
