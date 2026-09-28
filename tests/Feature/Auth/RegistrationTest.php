<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a new user and business successfully', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Acme Corp',
    ];

    $this->withoutExceptionHandling();
    $response = $this->postJson('/api/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
            ],
            'token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
    ]);

    $this->assertDatabaseHas('businesses', [
        'name' => 'Acme Corp',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    $business = Business::where('name', 'Acme Corp')->first();

    $this->assertDatabaseHas('business_user', [
        'user_id' => $user->id,
        'business_id' => $business->id,
        'role' => 'owner',
    ]);
});

it('fails to register with a duplicate email', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
    ]);

    $payload = [
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Beta Inc',
    ];

    $response = $this->postJson('/api/auth/register', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
