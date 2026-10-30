<?php

use App\Models\Business;
use App\Models\Location;
use App\Models\Shift;
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

test('enforces strict role boundaries preventing staff from calling manager or owner endpoints', function () {
    [$business, $owner] = createOwner();
    [, $manager] = createManager($business);
    [, $staff] = createStaff($business);

    $location = Location::factory()->create(['business_id' => $business->id]);
    $shift = Shift::factory()->create(['business_id' => $business->id]);

    // Staff cannot manage locations
    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/locations", ['name' => 'Unauthorized Branch'])
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/locations/{$location->id}", ['name' => 'Tampered'])
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/businesses/{$business->id}/locations/{$location->id}")
        ->assertForbidden();

    // Staff cannot mutate shifts or assign/remove staff
    $this->actingAs($staff, 'sanctum')
        ->patchJson("/api/businesses/{$business->id}/shifts/{$shift->id}", ['role_label' => 'Tampered'])
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/businesses/{$business->id}/shifts/{$shift->id}")
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/shifts/{$shift->id}/staff", ['user_id' => $staff->id])
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/businesses/{$business->id}/shifts/{$shift->id}/staff/{$staff->id}")
        ->assertForbidden();

    // Staff and Manager cannot initiate ownership transfer (Owner only)
    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/ownership-transfer", ['successor_email' => $manager->email])
        ->assertForbidden();

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/ownership-transfer", ['successor_email' => $staff->email])
        ->assertForbidden();
});
