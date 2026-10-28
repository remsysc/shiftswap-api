<?php

use App\Models\Location;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff] = createStaff($this->business);
});

test('owner can create a location', function (): void {
    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/locations", [
            'name' => 'Downtown Branch',
            'address' => '123 Main St, Cityville',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'business_id', 'name', 'address', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('data.name', 'Downtown Branch')
        ->assertJsonPath('data.address', '123 Main St, Cityville')
        ->assertJsonPath('data.business_id', $this->business->id);

    $this->assertDatabaseHas('locations', [
        'business_id' => $this->business->id,
        'name' => 'Downtown Branch',
    ]);
});

test('manager can create a location', function (): void {
    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/locations", [
            'name' => 'Uptown Branch',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Uptown Branch');
});

test('staff member cannot create a location', function (): void {
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/locations", [
            'name' => 'Forbidden Branch',
        ]);

    $response->assertForbidden();
});

test('all roles can view location list and single location', function (): void {
    $location = Location::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'North Branch',
    ]);

    // Staff can list
    $staffListResponse = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations");

    $staffListResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'North Branch');

    // Staff can view single
    $staffShowResponse = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations/{$location->id}");

    $staffShowResponse->assertOk()
        ->assertJsonPath('data.name', 'North Branch');

    // Owner can list
    $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations")
        ->assertOk();

    // Manager can list
    $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations")
        ->assertOk();
});

test('owner and manager can update a location', function (): void {
    $location = Location::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->patchJson("/api/businesses/{$this->business->id}/locations/{$location->id}", [
            'name' => 'New Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('locations', [
        'id' => $location->id,
        'name' => 'New Name',
    ]);
});

test('staff member cannot update a location', function (): void {
    $location = Location::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $response = $this->actingAs($this->staff, 'sanctum')
        ->patchJson("/api/businesses/{$this->business->id}/locations/{$location->id}", [
            'name' => 'Hacked Name',
        ]);

    $response->assertForbidden();
});

test('owner and manager can delete a location', function (): void {
    $location = Location::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'location_id' => $location->id,
    ]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/businesses/{$this->business->id}/locations/{$location->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('locations', [
        'id' => $location->id,
    ]);

    $shift->refresh();
    expect($shift->location_id)->toBeNull();
});

test('staff member cannot delete a location', function (): void {
    $location = Location::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $response = $this->actingAs($this->staff, 'sanctum')
        ->deleteJson("/api/businesses/{$this->business->id}/locations/{$location->id}");

    $response->assertForbidden();
});

test('cross-tenant user cannot access locations', function (): void {
    [$otherBusiness, $otherOwner] = createOwner();
    $location = Location::factory()->create(['business_id' => $this->business->id]);

    $response = $this->actingAs($otherOwner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations");

    $response->assertForbidden();

    $showResponse = $this->actingAs($otherOwner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations/{$location->id}");

    $showResponse->assertForbidden();
});

test('cannot access location belonging to another business even if member of route business', function (): void {
    [$otherBusiness] = createOwner();
    $otherLocation = Location::factory()->create(['business_id' => $otherBusiness->id]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/locations/{$otherLocation->id}");

    $response->assertForbidden();
});
