<?php

use App\Models\Location;
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
    [, $this->staff] = createStaff($this->business);
});

test('manager and owner can view aggregated dashboard (fr-49, nfr-3)', function (): void {
    $location = Location::factory()->create(['business_id' => $this->business->id]);

    // Upcoming shift within 7 days
    $upcomingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'location_id' => $location->id,
        'start_at' => Carbon::now()->addDays(2)->setTime(9, 0),
        'end_at' => Carbon::now()->addDays(2)->setTime(17, 0),
    ]);

    // Shift beyond 7 days (should not appear in 7-day upcoming)
    Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->addDays(10)->setTime(9, 0),
        'end_at' => Carbon::now()->addDays(10)->setTime(17, 0),
    ]);

    // Pending swap
    ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'status' => 'pending_approval',
    ]);

    // Approved swap (should not count towards pending)
    ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'status' => 'approved',
    ]);

    // Pending time off
    TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/dashboard");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'upcoming_shifts',
                'pending_swaps_count',
                'pending_time_off_count',
            ],
        ])
        ->assertJsonCount(1, 'data.upcoming_shifts')
        ->assertJsonPath('data.upcoming_shifts.0.id', $upcomingShift->id)
        ->assertJsonPath('data.pending_swaps_count', 1)
        ->assertJsonPath('data.pending_time_off_count', 1);

    // Owner gets same dashboard shape
    $ownerResponse = $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/dashboard");

    $ownerResponse->assertOk()
        ->assertJsonPath('data.pending_swaps_count', 1);
});

test('staff member views personal dashboard (fr-50)', function (): void {
    // Shift within 14 days assigned to staff
    $assignedShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->addDays(4)->setTime(9, 0),
        'end_at' => Carbon::now()->addDays(4)->setTime(17, 0),
    ]);
    ShiftStaff::factory()->create([
        'shift_id' => $assignedShift->id,
        'user_id' => $this->staff->id,
        'status' => 'assigned',
    ]);

    // Shift within 14 days NOT assigned to staff
    Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->addDays(4)->setTime(9, 0),
        'end_at' => Carbon::now()->addDays(4)->setTime(17, 0),
    ]);

    // Staff pending time off
    $pendingTimeOff = TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff->id,
        'status' => 'pending',
    ]);

    // Active swap where staff is offerer
    $activeSwap = ShiftSwap::factory()->create([
        'business_id' => $this->business->id,
        'offered_by' => $this->staff->id,
        'status' => 'offered',
    ]);

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/dashboard");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'upcoming_shifts',
                'pending_time_off_requests',
                'active_swaps',
            ],
        ])
        ->assertJsonCount(1, 'data.upcoming_shifts')
        ->assertJsonPath('data.upcoming_shifts.0.id', $assignedShift->id)
        ->assertJsonCount(1, 'data.pending_time_off_requests')
        ->assertJsonPath('data.pending_time_off_requests.0.id', $pendingTimeOff->id)
        ->assertJsonCount(1, 'data.active_swaps')
        ->assertJsonPath('data.active_swaps.0.id', $activeSwap->id);
});

test('cross-tenant user cannot view dashboard', function (): void {
    [$otherBusiness, $otherOwner] = createOwner();

    $this->actingAs($otherOwner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/dashboard")
        ->assertForbidden();
});
