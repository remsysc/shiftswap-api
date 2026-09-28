<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| SETUP-1 foundation health checks
|--------------------------------------------------------------------------
|
| These tests prove the Sprint 1 foundation is wired correctly: the /api
| prefix resolves, unauthenticated access is rejected as JSON, and a
| Bearer-token-authenticated request resolves the current user. They do
| not implement FR-9/FR-12 (login/logout) — only the plumbing.
|
*/

test('unauthenticated request to a protected api route returns 401 as json', function () {
    $response = $this->getJson('/api/auth/user');

    $response
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('bearer-token-authenticated request resolves the current user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'email' => $user->email,
        ]);
});

test('password and remember_token are hidden from api serialization', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response
        ->assertOk()
        ->assertJsonMissing(['password'])
        ->assertJsonMissing(['remember_token']);
});
