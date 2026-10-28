<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\TimeOffRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOffRequest>
 */
class TimeOffRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->addDays(fake()->numberBetween(3, 10));
        $endDate = (clone $startDate)->addDays(fake()->numberBetween(0, 4));

        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => fake()->optional()->sentence(),
            'status' => 'pending',
            'manager_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'manager_notes' => 'Approved by manager',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'manager_notes' => 'Rejected due to minimum staffing',
        ]);
    }
}
