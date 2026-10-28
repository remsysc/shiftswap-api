<?php

use App\Models\Business;
use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\User;
use App\Services\ShiftConflictService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new ShiftConflictService;
    $this->business = Business::factory()->create();
    $this->user = User::factory()->create();
});

test('returns false when user has no assigned shifts', function (): void {
    $start = Carbon::parse('2026-11-01 09:00:00');
    $end = Carbon::parse('2026-11-01 17:00:00');

    expect($this->service->hasShiftOverlap($this->user, $start, $end))->toBeFalse();
});

test('returns false when shifts touch at boundary (boundary equality allowed)', function (): void {
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->user->id,
        'status' => 'assigned',
    ]);

    // Starts exactly when existing ends
    $touchingAfterStart = Carbon::parse('2026-11-01 13:00:00');
    $touchingAfterEnd = Carbon::parse('2026-11-01 17:00:00');
    expect($this->service->hasShiftOverlap($this->user, $touchingAfterStart, $touchingAfterEnd))->toBeFalse();

    // Ends exactly when existing starts
    $touchingBeforeStart = Carbon::parse('2026-11-01 05:00:00');
    $touchingBeforeEnd = Carbon::parse('2026-11-01 09:00:00');
    expect($this->service->hasShiftOverlap($this->user, $touchingBeforeStart, $touchingBeforeEnd))->toBeFalse();
});

test('returns true when shifts overlap partially', function (): void {
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->user->id,
        'status' => 'assigned',
    ]);

    // Overlaps tail: 12:00 - 16:00
    $overlapTailStart = Carbon::parse('2026-11-01 12:00:00');
    $overlapTailEnd = Carbon::parse('2026-11-01 16:00:00');
    expect($this->service->hasShiftOverlap($this->user, $overlapTailStart, $overlapTailEnd))->toBeTrue();

    // Overlaps head: 08:00 - 10:00
    $overlapHeadStart = Carbon::parse('2026-11-01 08:00:00');
    $overlapHeadEnd = Carbon::parse('2026-11-01 10:00:00');
    expect($this->service->hasShiftOverlap($this->user, $overlapHeadStart, $overlapHeadEnd))->toBeTrue();
});

test('returns true when new shift encloses or is enclosed by existing shift', function (): void {
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->user->id,
        'status' => 'assigned',
    ]);

    // Enclosing: 08:00 - 14:00
    $enclosingStart = Carbon::parse('2026-11-01 08:00:00');
    $enclosingEnd = Carbon::parse('2026-11-01 14:00:00');
    expect($this->service->hasShiftOverlap($this->user, $enclosingStart, $enclosingEnd))->toBeTrue();

    // Enclosed: 10:00 - 11:00
    $enclosedStart = Carbon::parse('2026-11-01 10:00:00');
    $enclosedEnd = Carbon::parse('2026-11-01 11:00:00');
    expect($this->service->hasShiftOverlap($this->user, $enclosedStart, $enclosedEnd))->toBeTrue();
});

test('ignores soft-deleted shifts', function (): void {
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->user->id,
        'status' => 'assigned',
    ]);

    $existingShift->delete();

    $start = Carbon::parse('2026-11-01 10:00:00');
    $end = Carbon::parse('2026-11-01 12:00:00');
    expect($this->service->hasShiftOverlap($this->user, $start, $end))->toBeFalse();
});

test('ignores removed shift staff assignments', function (): void {
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->removed()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->user->id,
    ]);

    $start = Carbon::parse('2026-11-01 10:00:00');
    $end = Carbon::parse('2026-11-01 12:00:00');
    expect($this->service->hasShiftOverlap($this->user, $start, $end))->toBeFalse();
});

test('ignores overlapping shifts of different users', function (): void {
    $otherUser = User::factory()->create();

    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $otherUser->id,
        'status' => 'assigned',
    ]);

    $start = Carbon::parse('2026-11-01 10:00:00');
    $end = Carbon::parse('2026-11-01 12:00:00');
    expect($this->service->hasShiftOverlap($this->user, $start, $end))->toBeFalse();
});

test('ignores excluded shift id', function (): void {
    $existingShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::parse('2026-11-01 09:00:00'),
        'end_at' => Carbon::parse('2026-11-01 13:00:00'),
    ]);

    ShiftStaff::factory()->create([
        'shift_id' => $existingShift->id,
        'user_id' => $this->user->id,
        'status' => 'assigned',
    ]);

    $start = Carbon::parse('2026-11-01 10:00:00');
    $end = Carbon::parse('2026-11-01 12:00:00');
    expect($this->service->hasShiftOverlap($this->user, $start, $end, $existingShift->id))->toBeFalse();
});
