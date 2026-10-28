<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\OwnershipTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnershipTransfer>
 */
class OwnershipTransferFactory extends Factory
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
            'current_owner_id' => User::factory(),
            'successor_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(40)),
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
            'accepted_at' => null,
        ];
    }
}
