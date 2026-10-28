<?php

use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff] = createStaff($this->business);
});

test('manager can assign staff member to a shift (fr-20.1)', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 09:00:00'),
        'end_at' => Carbon::parse('2026-11-10 17:00:00'),
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff", [
            'user_id' => $this->staff->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'shift_id',
                'user_id',
                'status',
                'assigned_at',
                'removed_at',
                'removed_reason',
                'user' => ['id', 'name', 'email'],
            ],
        ])
        ->assertJsonPath('data.shift_id', $shift->id)
        ->assertJsonPath('data.user_id', $this->staff->id)
        ->assertJsonPath('data.status', 'assigned');

    $this->assertDatabaseHas('shift_staff', [
        'shift_id' => $shift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);
});

test('assigning staff member with overlapping shift returns 422 (fr-20.1, fr-21)', function (): void {
    // Existing shift: 09:00 - 15:00
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 09:00:00'),
        'end_at' => Carbon::parse('2026-11-10 15:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    // Conflicting new shift: 12:00 - 18:00
    $conflictingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 12:00:00'),
        'end_at' => Carbon::parse('2026-11-10 18:00:00'),
    ]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$conflictingShift->id}/staff", [
            'user_id' => $this->staff->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'This staff member has a conflicting shift or approved time-off.');
});

test('assigning staff member with boundary equality succeeds without conflict (fr-21)', function (): void {
    // Existing shift: 09:00 - 13:00
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 09:00:00'),
        'end_at' => Carbon::parse('2026-11-10 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    // Back-to-back shift: starts exactly at 13:00
    $adjacentShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-10 13:00:00'),
        'end_at' => Carbon::parse('2026-11-10 17:00:00'),
    ]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$adjacentShift->id}/staff", [
            'user_id' => $this->staff->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'assigned');
});

test('assigning already assigned user to the same shift returns 422', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff", [
            'user_id' => $this->staff->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'This staff member is already assigned to this shift.');
});

test('cannot assign a user who does not belong to the business', function (): void {
    $otherUser = User::factory()->create();
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff", [
            'user_id' => $otherUser->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'The selected user does not belong to this business.');
});

test('staff member cannot assign staff to a shift (fr-14.1)', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff", [
            'user_id' => $this->staff->id,
        ]);

    $response->assertForbidden();
});

test('manager can remove a staff assignment from a shift', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $assignment = ShiftStaff::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->deleteJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff/{$this->staff->id}");

    $response->assertNoContent();

    $assignment->refresh();
    expect($assignment->status)->toBe('removed');
    expect($assignment->removed_reason)->toBe('manager_removed');
    expect($assignment->removed_at)->not->toBeNull();
});

test('staff member cannot remove staff assignment from shift', function (): void {
    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->staff, 'sanctum')
        ->deleteJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff/{$this->staff->id}");

    $response->assertForbidden();
});
