<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('revokes current bearer token on logout and returns 204 (fr-12.1)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout');

    $response->assertNoContent();

    // Verify token was revoked
    expect($user->tokens()->count())->toBe(0);

    // Verify token can no longer authenticate
    app('auth')->forgetGuards();
    $protectedResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/user');

    $protectedResponse->assertUnauthorized();
});

test('returns 401 when unauthenticated user attempts to logout', function () {
    $response = $this->postJson('/api/auth/logout');

    $response->assertUnauthorized();
});
