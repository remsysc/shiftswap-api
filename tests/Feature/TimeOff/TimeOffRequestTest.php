<?php

use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use App\Models\TimeOffRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff1] = createStaff($this->business);
    [, $this->staff2] = createStaff($this->business);
});

test('staff member can submit a time-off request (fr-34.1)', function (): void {
    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests", [
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
            'reason' => 'Family vacation',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'business_id',
                'start_date',
                'end_date',
                'reason',
                'status',
                'user' => ['id', 'name', 'email'],
            ],
        ])
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.start_date', '2026-11-20')
        ->assertJsonPath('data.end_date', '2026-11-22')
        ->assertJsonPath('data.user.id', $this->staff1->id);

    $timeOff = TimeOffRequest::where('business_id', $this->business->id)
        ->where('user_id', $this->staff1->id)
        ->firstOrFail();

    expect($timeOff->status)->toBe('pending');
    expect($timeOff->start_date->toDateString())->toBe('2026-11-20');
    expect($timeOff->end_date->toDateString())->toBe('2026-11-22');
});

test('submitting time-off with end_date before start_date returns 422 (fr-35.1)', function (): void {
    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests", [
            'start_date' => '2026-11-25',
            'end_date' => '2026-11-20',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_date'])
        ->assertJsonPath('errors.end_date.0', 'End date must be on or after start date.');
});

test('submitting time-off overlapping an existing pending request returns 422 (fr-39.1)', function (): void {
    TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-24',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests", [
            'start_date' => '2026-11-22',
            'end_date' => '2026-11-26',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date'])
        ->assertJsonPath('errors.start_date.0', 'You already have a time-off request for this period.');
});

test('submitting time-off overlapping a rejected request is permitted', function (): void {
    TimeOffRequest::factory()->rejected()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-24',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests", [
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-24',
        ]);

    $response->assertStatus(201);
});

test('viewing a time-off request includes conflicts array of overlapping shifts (fr-36.1)', function (): void {
    $timeOff = TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-22',
    ]);

    // Shift falling on 2026-11-21 (conflict)
    $conflictingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-21 09:00:00'),
        'end_at' => Carbon::parse('2026-11-21 17:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $conflictingShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    // Shift falling on 2026-11-25 (no conflict)
    $nonConflictingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-25 09:00:00'),
        'end_at' => Carbon::parse('2026-11-25 17:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $nonConflictingShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/time-off-requests/{$timeOff->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'user', 'start_date', 'end_date', 'status'],
            'conflicts',
        ])
        ->assertJsonCount(1, 'conflicts')
        ->assertJsonPath('conflicts.0.id', $conflictingShift->id);
});

test('manager can approve time-off request (fr-37.1)', function (): void {
    $timeOff = TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests/{$timeOff->id}/approve", [
            'manager_notes' => 'Approved, have fun!',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.manager_notes', 'Approved, have fun!');

    $timeOff->refresh();
    expect($timeOff->status)->toBe('approved');
});

test('manager can reject time-off request (fr-38.1)', function (): void {
    $timeOff = TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests/{$timeOff->id}/reject", [
            'manager_notes' => 'Short staffed on this date',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.manager_notes', 'Short staffed on this date');

    $timeOff->refresh();
    expect($timeOff->status)->toBe('rejected');
});

test('staff member cannot approve or reject time-off requests', function (): void {
    $timeOff = TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
    ]);

    $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests/{$timeOff->id}/approve")
        ->assertForbidden();

    $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests/{$timeOff->id}/reject")
        ->assertForbidden();
});

test('staff only sees own time-off requests while manager sees all', function (): void {
    TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
    ]);

    TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff2->id,
    ]);

    // Staff1 views
    $staffResponse = $this->actingAs($this->staff1, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/time-off-requests");

    $staffResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $this->staff1->id);

    // Manager views
    $managerResponse = $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/time-off-requests");

    $managerResponse->assertOk()
        ->assertJsonCount(2, 'data');

    // Manager filters by user_id
    $filteredResponse = $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/time-off-requests?user_id={$this->staff2->id}");

    $filteredResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $this->staff2->id);
});

test('approved time-off blocks shift assignment via conflict service (fr-39a.1)', function (): void {
    TimeOffRequest::factory()->approved()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-22',
    ]);

    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-21 09:00:00'),
        'end_at' => Carbon::parse('2026-11-21 17:00:00'),
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff", [
            'user_id' => $this->staff1->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'This staff member has a conflicting shift or approved time-off.');
});

test('approved time-off blocks shift swap request via conflict service (fr-39a.1)', function (): void {
    TimeOffRequest::factory()->approved()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff2->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-22',
    ]);

    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-21 09:00:00'),
        'end_at' => Carbon::parse('2026-11-21 17:00:00'),
    ]);

    $assignment = ShiftStaff::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $shift->id,
        'shift_staff_id' => $assignment->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'You have a conflicting shift or approved time-off.');
});
