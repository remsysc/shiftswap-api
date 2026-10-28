<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('allows owner to view staff list (fr-13.1, fr-14.1)', function () {
    [$business, $owner] = createOwner();
    createStaff($business);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/businesses/{$business->id}/staff");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

test('allows manager to view staff list (fr-13.1, fr-14.1)', function () {
    [$business, $owner] = createOwner();
    [, $manager] = createManager($business);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson("/api/businesses/{$business->id}/staff");

    $response->assertOk();
});

test('denies staff from viewing staff list and returns 403 (fr-14.1)', function () {
    [$business, $owner] = createOwner();
    [, $staff] = createStaff($business);

    $response = $this->actingAs($staff, 'sanctum')
        ->getJson("/api/businesses/{$business->id}/staff");

    $response->assertForbidden();
});

test('allows owner to promote staff to manager (fr-15.1)', function () {
    [$business, $owner] = createOwner();
    [, $staff] = createStaff($business);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$staff->id}", [
            'role' => 'manager',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.role', 'manager');
    $this->assertDatabaseHas('business_user', [
        'business_id' => $business->id,
        'user_id' => $staff->id,
        'role' => 'manager',
    ]);
});

test('allows owner to demote manager to staff (fr-15.1)', function () {
    [$business, $owner] = createOwner();
    [, $manager] = createManager($business);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$manager->id}", [
            'role' => 'staff',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.role', 'staff');
    $this->assertDatabaseHas('business_user', [
        'business_id' => $business->id,
        'user_id' => $manager->id,
        'role' => 'staff',
    ]);
});

test('denies manager from updating staff roles and returns 403 (fr-14.1)', function () {
    [$business, $owner] = createOwner();
    [, $manager] = createManager($business);
    [, $staff] = createStaff($business);

    $response = $this->actingAs($manager, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$staff->id}", [
            'role' => 'manager',
        ]);

    $response->assertForbidden();
});

test('denies staff from updating roles and returns 403 (fr-14.1)', function () {
    [$business, $owner] = createOwner();
    [, $staffA] = createStaff($business);
    [, $staffB] = createStaff($business);

    $response = $this->actingAs($staffA, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$staffB->id}", [
            'role' => 'manager',
        ]);

    $response->assertForbidden();
});

test('prevents an owner from changing their own role and returns 422 (fr-16.1)', function () {
    [$business, $owner] = createOwner();

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$owner->id}", [
            'role' => 'manager',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['role']);
    $response->assertJson([
        'errors' => [
            'role' => [
                'Cannot change your own role.',
            ],
        ],
    ]);
});

test('rejects updating role to owner or invalid role and returns 422 (fr-15.1)', function () {
    [$business, $owner] = createOwner();
    [, $staff] = createStaff($business);

    // Target role cannot be owner (handled via ownership transfer)
    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$staff->id}", [
            'role' => 'owner',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['role']);

    // Unknown role
    $unknownResponse = $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/staff/{$staff->id}", [
            'role' => 'admin',
        ]);

    $unknownResponse->assertStatus(422);
    $unknownResponse->assertJsonValidationErrors(['role']);
});

test('returns 404 when updating role of a user not belonging to the business', function () {
    [$businessA, $ownerA] = createOwner();
    [$businessB, $otherUser] = createStaff();

    $response = $this->actingAs($ownerA, 'sanctum')
        ->patchJson("/api/businesses/{$businessA->id}/staff/{$otherUser->id}", [
            'role' => 'manager',
        ]);

    $response->assertNotFound();
});
