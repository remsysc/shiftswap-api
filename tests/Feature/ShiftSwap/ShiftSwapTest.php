<?php

use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff1] = createStaff($this->business);
    [, $this->staff2] = createStaff($this->business);

    $this->futureShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->addDays(3)->setTime(9, 0),
        'end_at' => Carbon::now()->addDays(3)->setTime(17, 0),
    ]);

    $this->assignment1 = ShiftStaff::factory()->create([
        'shift_id' => $this->futureShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);
});

test('staff member can offer an assigned future shift for swap (fr-24.1)', function (): void {
    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps", [
            'shift_id' => $this->futureShift->id,
            'notes' => 'Have a doctor appointment',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'business_id',
                'shift_id',
                'status',
                'notes',
                'offered_by' => ['id', 'name', 'email'],
                'requested_by',
                'created_at',
            ],
        ])
        ->assertJsonPath('data.status', 'offered')
        ->assertJsonPath('data.offered_by.id', $this->staff1->id)
        ->assertJsonPath('data.requested_by', null)
        ->assertJsonPath('data.notes', 'Have a doctor appointment');

    $this->assertDatabaseHas('shift_swaps', [
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);
});

test('unassigned staff member cannot offer shift for swap (fr-25.1)', function (): void {
    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps", [
            'shift_id' => $this->futureShift->id,
        ]);

    $response->assertForbidden();
});

test('cannot offer a past shift for swap (fr-26.1)', function (): void {
    $pastShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->subDays(2)->setTime(9, 0),
        'end_at' => Carbon::now()->subDays(2)->setTime(17, 0),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $pastShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps", [
            'shift_id' => $pastShift->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shift_id'])
        ->assertJsonPath('errors.shift_id.0', 'Cannot offer a past shift for swap.');
});

test('cannot create duplicate active swap offer for same assignment', function (): void {
    ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps", [
            'shift_id' => $this->futureShift->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shift_id'])
        ->assertJsonPath('errors.shift_id.0', 'An active swap offer already exists for this assignment.');
});

test('staff member can request an offered shift swap (fr-27.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request");

    $response->assertOk()
        ->assertJsonPath('data.status', 'pending_approval')
        ->assertJsonPath('data.requested_by.id', $this->staff2->id);

    $swap->refresh();
    expect($swap->status)->toBe('pending_approval');
    expect($swap->requested_by)->toBe($this->staff2->id);
});

test('staff member cannot request an offered swap if shift is in the past', function (): void {
    $pastShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->subDays(1)->setTime(9, 0),
        'end_at' => Carbon::now()->subDays(1)->setTime(17, 0),
    ]);

    $assignment = ShiftStaff::factory()->create([
        'shift_id' => $pastShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $pastShift->id,
        'shift_staff_id' => $assignment->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'Cannot claim a swap for a shift that has already started or passed.');
});

test('staff member with conflicting shift cannot request swap (fr-28.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    // Create overlapping shift for staff2
    $conflictingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => (clone $this->futureShift->start_at)->addHour(),
        'end_at' => (clone $this->futureShift->end_at)->addHour(),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $conflictingShift->id,
        'user_id' => $this->staff2->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'You have a conflicting shift or approved time-off.');
});

test('single-requester lock rejects subsequent request when swap is already pending (fr-29.1, fr-29a)', function (): void {
    [, $staff3] = createStaff($this->business);

    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($staff3, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'This shift is no longer available.');
});

test('offerer cannot request their own swap offer', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'You cannot request your own swap offer.');
});

test('manager can approve swap which reassigns shift and updates history (fr-30.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/approve", [
            'manager_notes' => 'Approved, thanks for covering',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.manager_notes', 'Approved, thanks for covering');

    // Offerer assignment marked removed
    $this->assignment1->refresh();
    expect($this->assignment1->status)->toBe('removed');
    expect($this->assignment1->removed_reason)->toBe('swap_approved');

    // Requester gets new assignment
    $this->assertDatabaseHas('shift_staff', [
        'shift_id' => $this->futureShift->id,
        'user_id' => $this->staff2->id,
        'status' => 'assigned',
    ]);
});

test('manager cannot approve swap if shift has already started or passed', function (): void {
    $pastShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->subDays(1)->setTime(9, 0),
        'end_at' => Carbon::now()->subDays(1)->setTime(17, 0),
    ]);

    $assignment = ShiftStaff::factory()->create([
        'shift_id' => $pastShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $pastShift->id,
        'shift_staff_id' => $assignment->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/approve", [
            'manager_notes' => 'Attempting to approve past shift',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'Cannot approve a swap for a shift that has already started or passed.');
});

test('staff member cannot approve swap (fr-14.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/approve");

    $response->assertForbidden();
});

test('manager can reject swap which reverts status to offered (fr-31.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/reject", [
            'manager_notes' => 'Cannot approve due to role mismatch',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'offered')
        ->assertJsonPath('data.requested_by', null)
        ->assertJsonPath('data.manager_notes', null);

    $swap->refresh();
    expect($swap->status)->toBe('offered');
    expect($swap->requested_by)->toBeNull();
});

test('staff member can cancel own offer while in offered status (fr-32.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/cancel");

    $response->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $swap->refresh();
    expect($swap->status)->toBe('cancelled');
});

test('cannot cancel swap when already being processed (fr-33.1)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/cancel");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'Cannot cancel a swap that is already being processed.');
});

test('other staff member cannot cancel someone elses swap offer', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/cancel");

    $response->assertForbidden();
});

test('claimant can withdraw their claim on a pending swap', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/withdraw");

    $response->assertOk()
        ->assertJsonPath('data.status', 'offered')
        ->assertJsonPath('data.requested_by', null);

    $swap->refresh();
    expect($swap->status)->toBe('offered');
    expect($swap->requested_by)->toBeNull();
});

test('other user cannot withdraw a claim they did not make', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $response = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/withdraw");

    $response->assertForbidden();
});

test('cannot withdraw claim if swap is not in pending_approval status', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/withdraw");

    $response->assertForbidden();
});

test('members can list shift swaps with optional status filter', function (): void {
    ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'status' => 'offered',
    ]);

    ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'status' => 'approved',
    ]);

    // All
    $responseAll = $this->actingAs($this->staff1, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shift-swaps");

    $responseAll->assertOk()
        ->assertJsonCount(2, 'data');

    // Filtered by status
    $responseFiltered = $this->actingAs($this->staff1, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shift-swaps?status=offered");

    $responseFiltered->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'offered');
});

test('cross-tenant user cannot view or mutate shift swaps', function (): void {
    [$otherBusiness, $otherOwner] = createOwner();

    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'status' => 'offered',
    ]);

    $this->actingAs($otherOwner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/shift-swaps")
        ->assertForbidden();

    $this->actingAs($otherOwner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request")
        ->assertForbidden();

    $this->actingAs($otherOwner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/approve")
        ->assertForbidden();
});

test('approving swap fails with 422 when requester gained a conflicting shift after requesting (fr-12, fr-28)', function (): void {
    $swap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'shift_id' => $this->futureShift->id,
        'shift_staff_id' => $this->assignment1->id,
        'offered_by' => $this->staff1->id,
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    // After staff2 requested the swap, staff2 is assigned to an overlapping shift
    $overlappingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => (clone $this->futureShift->start_at)->addHour(),
        'end_at' => (clone $this->futureShift->end_at)->addHour(),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $overlappingShift->id,
        'user_id' => $this->staff2->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/approve");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'Requesting staff member now has a conflicting shift or approved time-off.');

    // Original assignment remains intact
    $this->assignment1->refresh();
    expect($this->assignment1->status)->toBe('assigned');
});
