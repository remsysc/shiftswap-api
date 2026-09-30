
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('User successfully', function () {
    // create the payload
    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Acme Corp',
    ];

    // send the post request
    $response = $this->postJson('/api/auth/register', $payload);

    // assert
    $response->assertStatus(201);
    // assert: check if db actually stored the record
    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
    ]);
    $this->assertDatabaseHas('businesses', [
        'name' => 'Acme Corp',
    ]);
    $this->assertDatabaseHas('business_user', [
        'role' => 'owner',
    ]);

    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'email',
        ],
        'token',
    ]);
});
