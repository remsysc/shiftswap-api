<?php

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('allows access to business endpoints when user belongs to the business (fr-7.1)', function () {
    [$business, $owner] = createOwner();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/businesses/{$business->id}/staff");

    $response->assertOk();
});

test('returns 403 when authenticated user attempts to access a different business (fr-8.1)', function () {
    [$businessA, $ownerA] = createOwner();
    [$businessB, $ownerB] = createOwner();

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/businesses/{$businessB->id}/staff");

    $response->assertForbidden();
    $response->assertJson([
        'message' => 'This action is unauthorized.',
    ]);
});

test('returns 401 when unauthenticated user accesses business endpoints', function () {
    $business = Business::factory()->create();

    $response = $this->getJson("/api/businesses/{$business->id}/staff");

    $response->assertUnauthorized();
});

test('returns 404 when accessing a non-existent business', function () {
    [$business, $owner] = createOwner();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/businesses/99999/staff');

    $response->assertNotFound();
});

test('scopes staff listing strictly to the active business context (fr-7.1)', function () {
    [$businessA, $ownerA] = createOwner();
    [$businessB, $ownerB] = createOwner();
    [, $staffA] = createStaff($businessA);
    [, $staffB] = createStaff($businessB);

    $response = $this->actingAs($ownerA, 'sanctum')
        ->getJson("/api/businesses/{$businessA->id}/staff");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['email' => $ownerA->email]);
    $response->assertJsonFragment(['email' => $staffA->email]);
    $response->assertJsonMissing(['email' => $ownerB->email]);
    $response->assertJsonMissing(['email' => $staffB->email]);
});
