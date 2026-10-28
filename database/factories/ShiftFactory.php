<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = now()->addDays(fake()->numberBetween(1, 10))->setTime(9, 0);
        $endAt = (clone $startAt)->setTime(17, 0);

        return [
            'business_id' => Business::factory(),
            'location_id' => null,
            'role_label' => fake()->randomElement(['Server', 'Barista', 'Cook', 'Host']),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'created_by' => User::factory(),
        ];
    }
}
