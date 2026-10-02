
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Models\User;

test('User successfully created', function () {
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

test('cannot register with an existing email', function () {
    // Arrange :  create an existing user
    //
    User::factory()->create(['email' => 'jane@example.com']);

    // act: attempt to register with that same email
    $response = $this->postJson('/api/auth/register',
        [
            'name' => 'Another Jane',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'business_name' => 'Acme Two',
        ]);

    // Assert: 422 status

    $response->assertStatus(422)->assertJsonValidationErrors([
        'email' => 'The email has already been taken.',
    ]);

});
