<?php

use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use App\Models\TimeOffRequest;
use App\Notifications\SendShiftAssignedNotification;
use App\Notifications\SendSwapApprovedNotification;
use App\Notifications\SendSwapOfferedNotification;
use App\Notifications\SendSwapRejectedNotification;
use App\Notifications\SendSwapRequestedNotification;
use App\Notifications\SendTimeOffStatusNotification;
use App\Notifications\SendTimeOffSubmittedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff1] = createStaff($this->business);
    [, $this->staff2] = createStaff($this->business);
});

test('shift assignment dispatches queued notification to assigned user (fr-40, fr-41)', function (): void {
    Notification::fake();

    $shift = Shift::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shifts/{$shift->id}/staff", [
            'user_id' => $this->staff1->id,
        ])
        ->assertStatus(201);

    Notification::assertSentTo(
        $this->staff1,
        SendShiftAssignedNotification::class,
        function ($notification) use ($shift): bool {
            return $notification->shift->id === $shift->id;
        }
    );
});

test('swap offer dispatches notification only to eligible staff members (fr-42)', function (): void {
    Notification::fake();

    // Staff3 has a conflicting shift
    [, $staff3] = createStaff($this->business);

    $futureShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->addDays(2)->setTime(9, 0),
        'end_at' => Carbon::now()->addDays(2)->setTime(17, 0),
    ]);

    $assignment = ShiftStaff::factory()->create([
        'shift_id' => $futureShift->id,
        'user_id' => $this->staff1->id,
        'status' => 'assigned',
    ]);

    // Create conflict for staff3
    $conflictShift = Shift::factory()->create([
        'business_id' => $this->business->id,
        'start_at' => Carbon::now()->addDays(2)->setTime(10, 0),
        'end_at' => Carbon::now()->addDays(2)->setTime(14, 0),
    ]);
    ShiftStaff::factory()->create([
        'shift_id' => $conflictShift->id,
        'user_id' => $staff3->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps", [
            'shift_id' => $futureShift->id,
        ])
        ->assertStatus(201);

    // Eligible staff2 should receive notification
    Notification::assertSentTo($this->staff2, SendSwapOfferedNotification::class);

    // Offerer (staff1) should NOT receive notification
    Notification::assertNotSentTo($this->staff1, SendSwapOfferedNotification::class);

    // Conflicted staff (staff3) should NOT receive notification
    Notification::assertNotSentTo($staff3, SendSwapOfferedNotification::class);
});

test('swap request dispatches notification to managers (fr-43)', function (): void {
    Notification::fake();

    $shift = Shift::factory()->create(['business_id' => $this->business->id]);
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

    $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/request")
        ->assertOk();

    Notification::assertSentTo($this->manager, SendSwapRequestedNotification::class);
    Notification::assertSentTo($this->owner, SendSwapRequestedNotification::class);
});

test('swap approval dispatches notification to both offerer and requester (fr-44)', function (): void {
    Notification::fake();

    $shift = Shift::factory()->create(['business_id' => $this->business->id]);
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
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/approve", [
            'manager_notes' => 'All set!',
        ])
        ->assertOk();

    Notification::assertSentTo($this->staff1, SendSwapApprovedNotification::class);
    Notification::assertSentTo($this->staff2, SendSwapApprovedNotification::class);
});

test('swap rejection dispatches notification to requester only with manager note (fr-45)', function (): void {
    Notification::fake();

    $shift = Shift::factory()->create(['business_id' => $this->business->id]);
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
        'requested_by' => $this->staff2->id,
        'status' => 'pending_approval',
    ]);

    $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/shift-swaps/{$swap->id}/reject", [
            'manager_notes' => 'Not enough senior staff on floor',
        ])
        ->assertOk();

    Notification::assertSentTo(
        $this->staff2,
        SendSwapRejectedNotification::class,
        fn ($notif) => $notif->managerNote === 'Not enough senior staff on floor'
    );
    Notification::assertNotSentTo($this->staff1, SendSwapRejectedNotification::class);
});

test('time-off submission dispatches notification to managers (fr-46)', function (): void {
    Notification::fake();

    $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests", [
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
            'reason' => 'Rest',
        ])
        ->assertStatus(201);

    Notification::assertSentTo($this->manager, SendTimeOffSubmittedNotification::class);
    Notification::assertSentTo($this->owner, SendTimeOffSubmittedNotification::class);
});

test('time-off status update dispatches notification to requesting staff member (fr-47)', function (): void {
    Notification::fake();

    $timeOff = TimeOffRequest::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff1->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/time-off-requests/{$timeOff->id}/approve", [
            'manager_notes' => 'Approved',
        ])
        ->assertOk();

    Notification::assertSentTo($this->staff1, SendTimeOffStatusNotification::class);
});

test('user can view, read, and read-all in-app notifications (fr-48)', function (): void {
    $shift = Shift::factory()->create(['business_id' => $this->business->id]);

    // Send real database notification to staff1
    $this->staff1->notify(new SendShiftAssignedNotification($shift));

    expect($this->staff1->unreadNotifications()->count())->toBe(1);
    $notificationId = $this->staff1->unreadNotifications()->first()->id;

    // View notifications
    $listResponse = $this->actingAs($this->staff1, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/notifications");

    $listResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $notificationId)
        ->assertJsonPath('data.0.read_at', null);

    // Mark single as read
    $readResponse = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/notifications/{$notificationId}/read");

    $readResponse->assertNoContent();
    expect($this->staff1->unreadNotifications()->count())->toBe(0);

    // Send another notification
    $this->staff1->notify(new SendShiftAssignedNotification($shift));
    expect($this->staff1->unreadNotifications()->count())->toBe(1);

    // Mark all as read
    $readAllResponse = $this->actingAs($this->staff1, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/notifications/read-all");

    $readAllResponse->assertNoContent();
    expect($this->staff1->unreadNotifications()->count())->toBe(0);
});

test('user cannot mark another users notification as read', function (): void {
    $shift = Shift::factory()->create(['business_id' => $this->business->id]);
    $this->staff1->notify(new SendShiftAssignedNotification($shift));
    $notificationId = $this->staff1->unreadNotifications()->first()->id;

    $this->actingAs($this->staff2, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/notifications/{$notificationId}/read")
        ->assertNotFound();
});
