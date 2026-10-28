<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('login:127.0.0.1');
});

test('authenticates a user with valid credentials and returns a bearer token (fr-9.1)', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'email',
            'created_at',
        ],
        'token',
    ]);
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect($response->json('data.email'))->toBe('jane@example.com');
});

test('returns 401 when password does not match (fr-10.1)', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Invalid credentials.',
    ]);
});

test('returns 401 when email does not exist (fr-10.1)', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Invalid credentials.',
    ]);
});

test('validates required email and password fields', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email', 'password']);
});

test('rate limits failed login attempts after 5 failures in 60 seconds (fr-11.1)', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    // Perform 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(401);
    }

    // The 6th attempt must be throttled
    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(429);
    $response->assertJson([
        'message' => 'Too many login attempts.',
    ]);
});
