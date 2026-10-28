<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftStaff>
 */
class ShiftStaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'status' => 'assigned',
            'assigned_at' => now(),
            'removed_at' => null,
            'removed_reason' => null,
        ];
    }

    public function removed(string $reason = 'swap'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'removed',
            'removed_at' => now(),
            'removed_reason' => $reason,
        ]);
    }
}
