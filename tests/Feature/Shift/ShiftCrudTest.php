<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff] = createStaff($this->business);
});

test('manager and owner can create a shift (fr-17.1)', function (): void {
    $location = Location::factory()->create(['business_id' => $this->business->id]);

    $startAt = now()->addDays(2)->setTime(9, 0)->toIso8601String();
    $endAt = now()->addDays(2)->setTime(17, 0)->toIso8601String();

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts", [
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location_id' => $location->id,
            'role_label' => 'Lead Server',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'business_id',
                'location_id',
                'role_label',
                'start_at',
                'end_at',
                'created_by',
                'created_at',
                'updated_at',
                'staff',
            ],
        ])
        ->assertJsonPath('data.business_id', $this->business->id)
        ->assertJsonPath('data.location_id', $location->id)
        ->assertJsonPath('data.role_label', 'Lead Server')
        ->assertJsonPath('data.created_by', $this->manager->id);

    $this->assertDatabaseHas('shifts', [
        'business_id' => $this->business->id,
        'location_id' => $location->id,
        'role_label' => 'Lead Server',
        'created_by' => $this->manager->id,
    ]);
});

test('shift creation fails with 422 when end_at is not after start_at (fr-18.1)', function (): void {
    $time = now()->addDays(2)->setTime(12, 0)->toIso8601String();

    // Equal times
    $equalResponse = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts", [
            'start_at' => $time,
            'end_at' => $time,
        ]);

    $equalResponse->assertStatus(422)
        ->assertJsonValidationErrors(['end_at'])
        ->assertJsonPath('errors.end_at.0', 'End time must be after start time.');

    // End before start
    $earlierResponse = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts", [
            'start_at' => now()->addDays(2)->setTime(15, 0)->toIso8601String(),
            'end_at' => now()->addDays(2)->setTime(11, 0)->toIso8601String(),
        ]);

    $earlierResponse->assertStatus(422)
        ->assertJsonValidationErrors(['end_at'])
        ->assertJsonPath('errors.end_at.0', 'End time must be after start time.');
});

test('staff member cannot create a shift (fr-14.1)', function (): void {
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts", [
            'start_at' => now()->addDays(1)->setTime(9, 0)->toIso8601String(),
            'end_at' => now()->addDays(1)->setTime(17, 0)->toIso8601String(),
        ]);

    $response->assertForbidden();
});

test('listing shifts requires date range and eager loads assigned staff (fr-23.1)', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-05 09:00:00'),
        'end_at' => Carbon::parse('2026-11-05 17:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    // Missing query parameters
    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shifts")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);

    // Valid query range
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shifts?start_date=2026-11-01&end_date=2026-11-10");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $shift->id)
        ->assertJsonCount(1, 'data.0.staff')
        ->assertJsonPath('data.0.staff.0.user.id', $this->staff->id);
});

test('owner and manager can update a shift', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'role_label' => 'Busser',
    ]);

    $newEnd = (clone $shift->start_at)->addHours(4)->toIso8601String();

    $response = $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}", [
            'role_label' => 'Server',
            'end_at' => $newEnd,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.role_label', 'Server');

    $this->assertDatabaseHas('shifts', [
        'id' => $shift->id,
        'role_label' => 'Server',
    ]);
});

test('updating shift with invalid end_at returns 422 (fr-18.1)', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-05 12:00:00'),
        'end_at' => Carbon::parse('2026-11-05 18:00:00'),
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->patchJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}", [
            'end_at' => Carbon::parse('2026-11-05 11:00:00')->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_at'])
        ->assertJsonPath('errors.end_at.0', 'End time must be after start time.');
});

test('manager can delete shift which soft-deletes and cancels open swaps (fr-22.1)', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $assignment = ShiftStaff::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    $openSwap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $shift->id,
        'shift_staff_id' => $assignment->id,
        'offered_by' => $this->staff->id,
        'status' => 'offered',
    ]);

    $pendingSwap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $shift->id,
        'shift_staff_id' => $assignment->id,
        'offered_by' => $this->staff->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->deleteJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}");

    $response->assertNoContent();

    // Assert soft deleted
    $this->assertSoftDeleted('shifts', ['id' => $shift->id]);

    // Assert swaps cancelled
    $openSwap->refresh();
    $pendingSwap->refresh();
    expect($openSwap->status)->toBe('cancelled');
    expect($openSwap->cancellation_reason)->toBe('Shift deleted');
    expect($pendingSwap->status)->toBe('cancelled');
});

test('cross-tenant user cannot view or manage shifts', function (): void {
    [$otherBusiness, $otherOwner] = createOwner();
    $shift = Shift::factory()->create(['business_id' => $this->business->id]);

    $this->actingAs($otherOwner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shifts?start_date=2026-11-01&end_date=2026-11-10")
        ->assertForbidden();

    $this->actingAs($otherOwner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}")
        ->assertForbidden();

    $this->actingAs($otherOwner, 'sanctum')
        ->deleteJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}")
        ->assertForbidden();
});

test('updating shift time window fails with 422 when assigned staff member would have a conflict (fr-12)', function (): void {
    $shift1 = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 08:00:00'),
        'end_at' => Carbon::parse('2026-11-10 12:00:00'),
    ]);

    $shift2 = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 14:00:00'),
        'end_at' => Carbon::parse('2026-11-10 18:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $shift1->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $shift2->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    // Extending shift1 end_at to 15:00 overlaps shift2 (14:00 - 18:00) for assigned staff
    $response = $this->actingAs($this->manager, 'sanctum')
        ->patchJson("/api/businesses/{$this->business->id}/shifts/{$shift1->id}", [
            'end_at' => Carbon::parse('2026-11-10 15:00:00')->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_at']);
});
